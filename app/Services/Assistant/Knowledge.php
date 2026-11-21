<?php

namespace App\Services\Assistant;

use App\Models\AssistantFact;
use App\Models\Faq;
use App\Models\PriceList;
use App\Models\Service;
use App\Models\TeamMember;
use App\Services\Booking\BookingService;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Ce stie asistentul: continutul public al site-ului (servicii, echipa, intrebari frecvente,
 * abonamente, Wallet, programari) plus informatiile scrise de clinica in admin.
 * Textul este acelasi intre cereri, ca sa poata fi pastrat in cache la Anthropic.
 */
class Knowledge
{
    public const CACHE_KEY = 'assistant.knowledge';

    public static function forget(): void
    {
        foreach (array_keys(\App\Support\Locales::ALL) as $locale) {
            Cache::forget(self::CACHE_KEY.'.'.$locale);
        }
    }

    public function text(): string
    {
        // Pe limba: adresele din cunostinte au prefixul limbii vizitatorului (/en/..., /hu/...).
        return Cache::remember(self::CACHE_KEY.'.'.app()->getLocale(), now()->addMinutes(30), fn () => $this->build());
    }

    private function build(): string
    {
        $url = fn (string $path) => rtrim(site_url($path), '/').'/';
        $parts = [];

        $hours = array_values(array_filter((array) Settings::get('clinic.hours', [])));
        $parts[] = "## Clinica\n".implode("\n", array_filter([
            'Nume: '.Settings::get('clinic.name'),
            'Adresă: '.Settings::get('clinic.address').', '.Settings::get('clinic.city'),
            'Telefon: '.Settings::get('clinic.phone'),
            'Email: '.Settings::get('clinic.email'),
            $hours ? 'Program: '.implode('; ', $hours) : 'Program: nu este publicat pe site; pentru program exact clientul sună la clinică.',
            'Descriere: '.Settings::get('clinic.tagline'),
        ]));

        $parts[] = "## Programări\n"
            .'Programările se fac pe site, la '.$url('/programare').', ca cerere: clientul alege ziua și intervalul preferat '
            .'('.implode(', ', array_map(fn ($p) => $p[0].' '.$p[1].'–'.$p[2], BookingService::PERIODS)).'), '
            .'iar clinica îl sună sau îi scrie ca să confirme ora exactă. Programarea este fixă doar după confirmare. '
            .'Urgențele nu așteaptă formularul: se sună imediat la '.Settings::get('clinic.phone').'.';

        $services = Service::published()->with('category:id,name')->orderBy('sort')->get();
        $parts[] = "## Servicii\n".$services->map(function (Service $s) use ($url) {
            $text = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($s->excerpt ?: $s->body)))), 420);

            return '- '.$s->name.($s->category ? ' ('.$s->category->name.')' : '').': '.$text.' Pagina: '.$url('/'.$s->slug);
        })->implode("\n");

        $team = TeamMember::query()->where('is_published', true)->orderBy('sort')->get();
        if ($team->isNotEmpty()) {
            $parts[] = "## Echipa\n".$team->map(fn ($m) => '- '.$m->name.($m->role_title ? ', '.$m->role_title : ''))->implode("\n")
                ."\nDetalii: ".$url('/echipa');
        }

        $list = PriceList::query()->where('is_active', true)->latest('effective_from')->first();
        if ($list) {
            $list->loadMissing('tiers.services');
            $lines = [];
            foreach ($list->tiers as $t) {
                $species = $t->species === 'cat' ? 'Pisică' : 'Câine';
                $range = rtrim(rtrim(number_format((float) $t->min_kg, 2, ',', ''), '0'), ',').'–'.rtrim(rtrim(number_format((float) $t->max_kg, 2, ',', ''), '0'), ',').' kg';
                $included = $t->services->map(fn ($s) => $s->display_name.($s->quantity_label ? ' ('.$s->quantity_label.')' : ''))->implode(', ');
                $lines[] = "- {$species}, categoria {$t->label} ({$range}): {$t->price_monthly} lei/lună, {$t->price_6m} lei pe 6 luni, {$t->price_12m} lei pe 12 luni. Include: {$included}.";
            }
            $rules = $list->multi_pet_rules ?? [];
            $multi = 'Al doilea animal: '.($rules['different_tier_percent']['2'] ?? 30).'% reducere, al treilea: '.($rules['different_tier_percent']['3'] ?? 40)
                .'%, al patrulea: '.($rules['different_tier_percent']['4'] ?? 50).'% (dacă are altă categorie de greutate decât animalele deja abonate; în aceeași categorie se aplică regula din contract).';
            $parts[] = "## Abonamente (lista de prețuri {$list->version})\n"
                ."Abonamentul este un plan de servicii veterinare plătit lunar, pe 6 sau pe 12 luni. NU este o asigurare și nu trebuie numit asigurare.\n"
                .implode("\n", $lines)."\n".$multi."\n"
                .($list->notes ? 'Note: '.$list->notes."\n" : '')
                .'Calculatorul de preț și contractul se găsesc la '.$url('/abonamente').'. Prețul final îl calculează site-ul la semnarea contractului.';
        }

        $parts[] = "## Wallet\n"
            .'Wallet-ul este un portofel în contul de client. La o încărcare de X lei clientul primește X lei sold principal, X lei bonus pentru servicii și X/2 lei bonus pentru produse. '
            .'Bonusul poate acoperi cel mult jumătate dintr-un serviciu și cel mult o treime dintr-un produs. Wallet-ul se poate alimenta doar cu un abonament activ, '
            .'online cu cardul (dacă plata online este pornită) sau la clinică. Punctele și bonusurile nu sunt bani și nu se retrag în numerar. Contul: '.$url('/acces-cont');

        $faqs = Faq::query()->where('is_published', true)->orderBy('sort')->get();
        if ($faqs->isNotEmpty()) {
            $parts[] = "## Întrebări frecvente (de pe site)\n".$faqs->map(fn ($f) => 'Î: '.trim($f->question)."\nR: ".Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($f->answer))), 700))->implode("\n");
        }

        $facts = AssistantFact::query()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
        if ($facts->isNotEmpty()) {
            $parts[] = "## Informații adăugate de clinică (au prioritate)\n".$facts->map(fn ($f) => '### '.$f->title."\n".trim($f->content))->implode("\n");
        }

        $parts[] = "## Pagini utile\n".implode("\n", [
            '- Programare: '.$url('/programare'), '- Abonamente: '.$url('/abonamente'), '- Servicii: '.$url('/servicii'),
            '- Echipa: '.$url('/echipa'), '- Contact: '.$url('/hikevet-contact'), '- Întrebări frecvente: '.$url('/intrebari-frecvente'),
            '- Video Vet (consult video): '.$url('/video-vet'), '- Cont client: '.$url('/acces-cont'),
        ]);

        return implode("\n\n", $parts);
    }
}
