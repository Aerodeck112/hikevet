<?php

namespace App\Support;

/**
 * Textele folosite in scripturi (resources/js, functia t()). Se trimit paginii doar traducerile lor,
 * nu tot dictionarul. Testul I18nTest verifica sa nu lipseasca nicio cheie.
 */
class JsStrings
{
    public const KEYS = [
        'Cât costă abonamentul pentru un câine de 20 kg?',
        'Cum fac o programare?',
        'Ce include abonamentul?',
        'Cum funcționează Wallet-ul?',
        'Prea multe întrebări într-un minut. Mai încearcă puțin mai târziu.',
        'Nu am putut răspunde acum.',
        'Telefon: :phone.',
        'Conexiunea a căzut.',
        'Conexiunea a căzut. Încearcă din nou sau sună la :phone.',
        'Azi',
        'Mâine',
        'Consultație',
        'Oricare medic disponibil',
        'ora :time',
        'Miau, salut :name!',
        'Salut, :name! Ham!',
        ':name e de-al meu! Ham-ham!',
        'Un coleg de-al meu! Ham!',
        ':name, o colegă! Am grijă de ea.',
        'Miau! O pisică, ce bine.',
        'Oh, ceva nou! Abia aștept să-l cunosc.',
        'Notat: :service.',
        'Nicio grijă, medicul vede la consult.',
        ':day. Îmi notez în calendar!',
        'Dimineața? Eu sunt treaz de la 6.',
        'La prânz, după masa mea. Perfect.',
        'După-amiaza e cea mai bună tură.',
        'Seara e liniște în clinică.',
        'Perfect!',
        'Completează numele animăluțului și bifează acordul.',
        'Alege specia și sexul.',
        'Selectează o dată de naștere validă și rasa (sau scrie rasa).',
        'Greutatea câinelui trebuie să fie între 1,25 și 60 kg.',
        'Greutatea pisicii trebuie să fie între 0,5 și 70 kg.',
        'Completează toate câmpurile medicale marcate cu *.',
        'Scrie afecțiunea cronică.',
        'Completează corect numele, emailul, telefonul și adresa.',
        'CNP-ul trebuie să aibă exact 13 cifre.',
        'Completează seria și numărul cărții de identitate. Microcipul este opțional.',
        'Sesiunea formularului a expirat. Reîncarcă pagina și încearcă din nou.',
        'Prea multe încercări. Așteaptă un minut și încearcă din nou.',
        'A apărut o eroare. Încearcă din nou.',
        'Calculează oferta înainte de a continua.',
        'Bifează toate confirmările și semnează în chenar.',
        'Crăciun fericit! Miau!',
        'Crăciun fericit! Ham-ham!',
        'La mulți ani! Fără artificii lângă noi, te rog.',
        'E duminică. Cererile le citim de luni dimineață.',
        'Zzz… e noapte. Și tu ești treaz?',
        'E noapte, dar eu păzesc clinica.',
        'Bună dimineața! Încă îmi beau laptele.',
        'Bună dimineața! Am alergat deja de două ori prin curte.',
        'Bună seara! Eu abia acum mă trezesc.',
        'Bună seara! Clinica s-a închis, dar cererile le primim.',
        'Purrr…',
        'Mai la stânga. Acolo!',
        'Bine, încă puțin.',
        'Ce bine e!',
        'Nu te opri!',
        'Coada mea nu se mai oprește.',
        ':count azi',
        'Mmm! Mulțumesc!',
        'Încă una? Te rog!',
        'Ultima, promit!',
        'Bine, ultima-ultima.',
        'Gata! Medicul zice că ajunge. Sănătate întâi!',
        'Gata pe azi. Mâine primesc iar!',
        'Ai citit tot! Bravo, primești o lăbuță.',
        'Mă cheamă cineva? Miau!',
        'M-ai strigat? Ham-ham!',
        'Ham! Eu sunt Hiko.',
        'Ham-ham!',
        'Mă scarpini?',
        'Vin la control!',
        'Raza e pisica de sus. Nu-i spune că am zis.',
    ];

    /** @return array<string, string> */
    public static function dictionary(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => __($k)])->filter(fn ($v, $k) => $v !== $k)->all();
    }
}
