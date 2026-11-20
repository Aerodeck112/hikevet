<?php

namespace App\Support;

/**
 * Raza (pisica cu ochelari) si Hiko (ciobanescul) de pe fatada, pe paginile interioare.
 * Fiecare sectiune are personajul ei si cateva replici; la click personajul spune urmatoarea.
 */
class Buddies
{
    /** Prima potrivire castiga; cheile sunt prefixe de cale, fara slash la inceput. */
    private const BY_PATH = [
        'servicii' => ['dog', 'peek', ['Eu vin pentru vaccin. Tu?', 'Am auzit că aici nu doare deloc.', 'Eu sunt Hiko, ciobănescul de serviciu. Alege, te aștept!']],
        'abonamente' => ['cat', 'sit', ['Eu am abonament pe 12 luni.', 'Controalele mele sunt incluse. Miau.', 'Trage de cursor mai jos și uite cât cresc!']],
        'echipa' => ['cat', 'sit', ['Pe toți îi știu după miros.', 'Doctorii mei preferați. Nu le spune.', 'Eu sunt Raza. Ei au grijă de mine, eu am grijă de ochelari.']],
        'blog' => ['cat', 'sit', ['Citesc tot. Mai ales partea cu recompensele.', 'Miau. Articol bun, recomand.', 'Eu citesc cu ochelarii pe nas.']],
        'intrebari-frecvente' => ['dog', 'peek', ['Întreabă-mă orice!', 'Știu răspunsul. Cred. Ham?', 'Dacă nu găsești ce cauți, sună-ne.']],
        'hikevet-contact' => ['dog', 'peek', ['Sună-ne! Eu dau din coadă la telefon.', 'Ne găsești ușor: urmează mirosul de recompense.', 'Eu sunt Hiko. Te aștept la ușă!']],
        'contact' => ['dog', 'peek', ['Sună-ne! Eu dau din coadă la telefon.', 'Te aștept la ușă!']],
        'video-vet' => ['cat', 'sit', ['Consult din fotoliu. Îmi place.', 'Eu stau în cadru, tu pui întrebările.']],
        'tur-3d' => ['dog', 'peek', ['Hai înăuntru, îți arăt tot!', 'Sala de așteptare e preferata mea.']],
        'acces-cont/parola-uitata' => ['cat', 'sit', ['Se întâmplă. Și eu uit unde am pus jucăria.', 'Îți trimitem un link, tu doar verifici emailul.']],
        'acces-cont/parola-noua' => ['cat', 'sit', ['Alege o parolă bună. Nu „miau123”.', 'Gata, nu ți-o mai uit eu. Miau.']],
        'acces-cont' => ['cat', 'sit', ['Doar cu parola. Sunt Raza, pisica de pază.', 'Nu spun nimănui parola ta. Miau.', 'Te-am recunoscut după pași.']],
        'panou-cont' => ['dog', 'peek', ['Ham! Ce bine că ai venit!', 'Aici îți ții animăluții, contractele și Wallet-ul.', 'Ai grijă de bonusuri. Și de mine.']],
        'programare' => ['dog', 'peek', ['Abia aștept să ne vedem!', 'Alege ora, eu aștept la ușă.', 'Adu și jucăria preferată!']],
        'anexa' => ['cat', 'sleep', ['Zzz… anexele mă adorm de tot.', 'Citește tu, eu țin pernuța caldă.']],
        'termeni' => ['cat', 'sleep', ['Zzz… documentele mă adorm.', 'Citește tu, eu țin pernuța caldă.', 'Miau? A, încă citești. Bravo.']],
        'contract' => ['cat', 'sleep', ['Zzz… documentele mă adorm.', 'Citește tu, eu țin pernuța caldă.']],
        'politica' => ['cat', 'sleep', ['Zzz… documentele mă adorm.', 'Citește tu, eu țin pernuța caldă.']],
        'ghid-proprietar' => ['dog', 'peek', ['Ghidul e pentru tine. Eu am deja diplomă de băiat cuminte.']],
    ];

    /** Pentru paginile fara intrare: alternam intre pisica si caine, stabil pe aceeasi pagina. */
    private const FALLBACK = [
        ['cat', 'sit', ['Miau! Eu sunt Raza. Bine ai venit!', 'Ochelarii sunt de soare, nu de vedere.']],
        ['dog', 'peek', ['Ham! Eu sunt Hiko. Bine ai venit!', 'Mă scarpini? Doar puțin.']],
    ];

    /** @return array{kind: string, pose: string, say: list<string>} */
    public static function forPath(?string $path = null): array
    {
        $path = trim($path ?? request()->path(), '/');
        foreach (self::BY_PATH as $prefix => [$kind, $pose, $say]) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/') || str_starts_with($path, $prefix.'-')) {
                return compact('kind', 'pose', 'say');
            }
        }
        [$kind, $pose, $say] = self::FALLBACK[crc32($path) % 2];

        return compact('kind', 'pose', 'say');
    }
}
