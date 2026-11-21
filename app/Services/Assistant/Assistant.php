<?php

namespace App\Services\Assistant;

use App\Models\AssistantLog;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/** Asistentul de pe site: verifica limitele, intreaba modelul, noteaza intrebarea si formateaza raspunsul. */
class Assistant
{
    public const MAX_TURNS = 12;

    public const MAX_CHARS = 800;

    public function __construct(private readonly AssistantClient $client, private readonly Knowledge $knowledge) {}

    public static function enabled(): bool
    {
        return (bool) Settings::get('assistant.enabled') && filled(Settings::get('assistant.api_key'));
    }

    public function instructions(): string
    {
        $phone = (string) Settings::get('clinic.phone');
        $name = (string) Settings::get('clinic.name');

        return <<<TXT
        Ești asistentul de pe site-ul clinicii veterinare {$name} din Târgu Mureș. Pe site apari ca Hiko, ciobănescul clinicii, împreună cu Raza, pisica cu ochelari; vorbește cald și pe scurt, ca un recepționer prietenos, fără să exagerezi cu glumele.

        Scrie mereu în limba română, cu diacritice. Răspunde în 1–4 fraze scurte; folosește o listă doar când enumeri prețuri sau pași. Poți folosi **îngroșat** și linkuri în forma [text](adresă), doar cu adresele din secțiunea „Pagini utile” sau din paginile de servicii.

        Folosește doar informațiile de mai jos despre clinică. Dacă nu găsești răspunsul acolo (de exemplu un preț pentru un serviciu care nu e în listă, disponibilitatea exactă a unui medic sau programul, dacă nu e trecut), spune că nu știi sigur și îndrumă clientul să sune la {$phone} sau să trimită o cerere de programare. Nu inventa prețuri, nume, orare sau servicii.

        Nu pui diagnostice, nu recomanzi medicamente sau doze și nu spui dacă o problemă e gravă sau nu; explici că doar medicul poate evalua animalul la consult. Poți da sfaturi generale de îngrijire doar dacă apar în informațiile de mai jos.

        Dacă mesajul descrie o urgență (animal lovit, care sângerează, nu respiră bine, convulsii, otrăvire sau substanță toxică înghițită, nu urinează, balonare, colaps, căldură puternică), răspunde din prima frază să sune imediat la {$phone} și să vină la clinică; nu întreba alte detalii înainte.

        Abonamentul este un plan de servicii veterinare, nu o asigurare: nu folosi cuvântul „asigurare” pentru el.

        Nu cere și nu accepta date personale sensibile (CNP, date de card, parole). Pentru programare, trimite clientul la formularul de cerere de programare. Dacă cineva întreabă altceva decât lucruri legate de animale și de clinică, spune politicos că poți ajuta doar cu informații despre clinică și îngrijirea animalelor de companie.
        TXT;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages  conversatia trimisa de browser
     * @return array{answer: string, html: string, status: string}
     */
    public function ask(array $messages, string $conversation, string $visitor): array
    {
        $messages = $this->clean($messages);
        $question = end($messages)['content'];

        $dayKey = 'assistant.count.'.now()->format('Ymd').'.'.hash('sha256', $visitor.config('app.key'));
        $count = (int) Cache::get($dayKey, 0);
        $limit = (int) (Settings::get('assistant.daily_limit') ?: 30);
        if ($count >= $limit) {
            return $this->canned('limit', $question, $conversation, 'Pentru azi am răspuns la multe întrebări. Pentru alte detalii, sună-ne la '.Settings::get('clinic.phone').' sau trimite o cerere de programare.');
        }
        Cache::put($dayKey, $count + 1, now()->endOfDay());

        $reply = $this->client->reply($this->instructions(), $this->knowledge->text(), $messages);

        $fallback = match ($reply->status) {
            'refused' => 'La asta nu te pot ajuta aici. Pentru orice întrebare despre animalul tău, sună-ne la '.Settings::get('clinic.phone').'.',
            'busy' => 'Sunt foarte mulți vizitatori acum. Încearcă din nou peste un minut sau sună-ne la '.Settings::get('clinic.phone').'.',
            'answered' => null,
            default => 'Nu am putut răspunde acum. Sună-ne la '.Settings::get('clinic.phone').', te ajutăm direct.',
        };
        $answer = $fallback ?? $reply->text;

        AssistantLog::create([
            'conversation' => $conversation, 'question' => $question, 'answer' => $answer, 'status' => $reply->status, 'model' => $reply->model ?: null,
            'input_tokens' => $reply->inputTokens, 'output_tokens' => $reply->outputTokens, 'cache_read_tokens' => $reply->cacheReadTokens,
        ]);

        return ['answer' => $answer, 'html' => $this->toHtml($answer), 'status' => $reply->status];
    }

    /** @return list<array{role: string, content: string}> */
    private function clean(array $messages): array
    {
        $out = [];
        foreach (array_slice(array_values($messages), -self::MAX_TURNS) as $m) {
            $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = Str::limit(trim((string) ($m['content'] ?? '')), $role === 'user' ? self::MAX_CHARS : 2000, '');
            if ($content !== '') {
                $out[] = ['role' => $role, 'content' => $content];
            }
        }
        // Conversatia trebuie sa inceapa si sa se termine cu un mesaj al clientului.
        while ($out && $out[0]['role'] !== 'user') {
            array_shift($out);
        }
        if (! $out || end($out)['role'] !== 'user') {
            throw new RuntimeException('Scrie o întrebare.');
        }

        return $out;
    }

    private function canned(string $status, string $question, string $conversation, string $answer): array
    {
        AssistantLog::create(['conversation' => $conversation, 'question' => $question, 'answer' => $answer, 'status' => $status]);

        return ['answer' => $answer, 'html' => $this->toHtml($answer), 'status' => $status];
    }

    /** Text simplu -> HTML sigur: paragrafe, liste, **ingrosat** si linkuri doar spre site-ul clinicii. */
    public function toHtml(string $text): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
        $html = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) use ($host) {
            $url = html_entity_decode($m[2]);
            $ok = str_starts_with($url, '/') || (parse_url($url, PHP_URL_HOST) === $host) || str_starts_with($url, 'tel:');

            return $ok ? '<a href="'.e($url).'">'.$m[1].'</a>' : $m[1];
        }, $html);

        $blocks = preg_split('/\n{2,}/', trim($html));
        $out = '';
        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), 'strlen'));
            if ($lines && count(array_filter($lines, fn ($l) => preg_match('/^(-|\*|\d+\.)\s+/', $l))) === count($lines)) {
                $out .= '<ul>'.implode('', array_map(fn ($l) => '<li>'.preg_replace('/^(-|\*|\d+\.)\s+/', '', $l).'</li>', $lines)).'</ul>';
            } else {
                $out .= '<p>'.implode('<br>', $lines).'</p>';
            }
        }

        return $out;
    }
}
