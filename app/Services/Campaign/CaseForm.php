<?php

namespace App\Services\Campaign;

/**
 * Formularele dosarului, cu etichetele si ordinea din plugin (section_receptie, section_preoperator,
 * section_interventie, section_externare, section_validare, section_corectare).
 * Un camp: [nume, eticheta, tip, optiuni, obligatoriu, latime_completa, doar_citire].
 */
final class CaseForm
{
    public const TABS = [
        'receptie' => '1. Programare + consimțământ',
        'preoperator' => '2. Fișa pacientului / consultație',
        'interventie' => '3. Intervenție',
        'externare' => '4. Externare & follow-up',
        'validare' => '5. Validare Primărie',
        'corectare' => '✎ Corectare dosar',
        'rezumat' => 'Rezumat pacient',
        'istoric' => 'Istoric',
    ];

    private static function f(string $name, string $label, string $type = 'text', string|array|null $options = null, bool $required = false, bool $full = false, bool $readonly = false): array
    {
        return compact('name', 'label', 'type', 'options', 'required', 'full', 'readonly');
    }

    /** @return list<array{title:string,note?:string,fields:list<array>}> */
    public static function cards(string $tab, bool $isNew = false): array
    {
        $f = fn (...$a) => self::f(...$a);

        return match ($tab) {
            'receptie' => $isNew ? [[
                'title' => 'Programare telefonică', 'note' => 'Completează datele minime. La salvare se creează automat programarea și dosarul de campanie.',
                'fields' => [
                    $f('cod_caz', 'Cod caz', 'text', null, false, false, true), $f('data_programare', 'Data programării', 'date', null, true),
                    $f('ora_programare', 'Ora programării', 'time', null, true), $f('nume_proprietar', 'Nume și prenume proprietar', 'text', null, true),
                    $f('telefon', 'Telefon', 'tel', null, true), $f('email', 'Email proprietar', 'email', null, true), $f('nume_pacient', 'Nume pacient', 'text', null, true),
                    $f('specie', 'Specie', 'select', 'specie', true), $f('sex', 'Sex', 'select', 'sex', true), $f('vaccinat', 'Vaccinat', 'radio', 'yesno', true),
                    $f('procedura_programata', 'Intervenție programată', 'select', 'procedura', true),
                ],
            ]] : [[
                'title' => 'Recepție – date proprietar, documente și consimțământ',
                'note' => 'După consult, proprietarul completează aici doar datele legale. Consimțământul și GDPR sunt același document digital; după semnarea pe tabletă, statusul devine automat Semnat.',
                'fields' => [
                    $f('email', 'Email proprietar', 'email', null, true), $f('adresa_ci', 'Adresa din CI', 'text', null, true, true),
                    $f('serie_numar_ci', 'Serie și număr CI', 'text', null, true), $f('localitate', 'Localitate', 'text', null, true),
                    $f('documente_scanate', 'CI scanat / fotografiat (JPG, PNG, WEBP sau PDF)', 'file_ci', null, false, true),
                ],
            ]],
            'preoperator' => [[
                'title' => 'Consult pacient', 'note' => 'Aceasta este prima parte a dosarului medical: consultația pacientului și verificarea preoperatorie.',
                'fields' => [
                    $f('nume_pacient', 'Nume pacient', 'text', null, true), $f('rasa', 'Rasa', 'text', null, true), $f('varsta_estimata', 'Vârsta pacientului'),
                    $f('specie', 'Specie (preluată din programare)', 'text', null, true, false, true), $f('sex', 'Sex (preluat din programare)', 'text', null, true, false, true),
                    $f('animal_cu_stapan', 'Animal cu stăpân / fără stăpân', 'select', 'stapan'), $f('vaccinat', 'Vaccinat (preluat din programare)', 'radio', 'yesno'),
                    $f('habitat', 'Habitat', 'select', 'habitat'), $f('comportament_neobisnuit', 'Comportament neobișnuit în ultimele zile', 'radio', 'yesno'),
                    $f('comportament_detalii', 'Detalii comportament'), $f('deparazitat', 'Deparazitat', 'radio', 'yesno'), $f('deparazitare_detalii', 'Detalii deparazitare'),
                    $f('diaree_voma', 'Diaree sau vomă în ultimele zile', 'radio', 'yesno'), $f('diaree_voma_detalii', 'Detalii diaree / vomă'),
                    $f('tip_hrana_select', 'Tip hrană', 'select', 'hrana'), $f('tip_hrana_detalii', 'Detalii hrană'),
                    $f('greutate_reala', 'Greutate reală (kg) – acceptă 5,5 sau 5/2', 'text', null, true), $f('temperatura', 'Temperatura (°C) – acceptă 38,5'),
                    $f('frecventa_respiratorie', 'FR (respirații/minut)'), $f('frecventa_cardiaca', 'FC (bătăi/minut)'), $f('culoare_mucoase', 'Culoare mucoase aparente'),
                    $f('istoric_medical', 'Boli cronice / alergii / medicație curentă / intervenții chirurgicale', 'textarea', null, false, true),
                    $f('microcip', 'Microcip'), $f('pacient_apt', 'Pacient apt pentru intervenție', 'radio', 'yesno'), $f('motiv_amanare', 'Dacă nu este apt: motiv'),
                    $f('mentiuni_consult_preoperatorii', 'Mențiuni medicale', 'textarea', null, false, true), $f('responsabil_caz', 'Doctor responsabil', 'select', 'doctori', true),
                ],
            ]],
            'interventie' => [
                ['title' => 'Intervenție intra/postoperatorie', 'note' => 'Data intervenției pornește din programare, dar poate fi corectată. Pentru validarea la Primărie, mențiunile intra/postoperatorii sunt obligatorii: dacă nu există observații, scrieți „Fără observații”.',
                    'fields' => [
                        $f('data_interventie', 'Data intervenției', 'date', null, true), $f('ora_start', 'Ora început', 'time', null, true), $f('ora_final', 'Ora final', 'time'),
                        $f('procedura_efectuata', 'Procedura (preluată din programare)', 'text', null, false, false, true),
                        $f('medic_operator', 'Medic operator', 'select', 'doctori', true), $f('tehnician_asistent', 'Asistent', 'select', 'asistenti'),
                        $f('mentiuni_intra_postoperatorii', 'Mențiuni intra/postoperatorii', 'textarea', null, true, true),
                    ]],
                ['title' => 'Substanțe și doze', 'fields' => [
                    $f('tip_anestezie', 'Tip anestezie generală', 'select', 'anestezie', true), $f('protocol_anestezic', 'Protocol anestezic', 'textarea', null, false, true),
                    $f('substanta1', 'Substanța 1', 'text', null, true), $f('doza1', 'Doza 1'), $f('substanta2', 'Substanța 2'), $f('doza2', 'Doza 2'),
                    $f('substanta3', 'Substanța 3'), $f('doza3', 'Doza 3'), $f('alte_substante', 'Alte substanțe / doze', 'textarea', null, false, true),
                ]],
                ['title' => 'Poza pacient', 'note' => 'Numărul din fotografie este sursa de adevăr. Introdu exact numărul vizibil pe fotografie. Dacă îl corectezi manual, sistemul îl păstrează exact și nu îl mai schimbă automat la salvările ulterioare.',
                    'fields' => [$f('numar_pacient', 'Număr pacient', 'text', null, true), $f('poza_pacient', 'Încarcă de pe calculator (JPG, PNG, WEBP)', 'file_photo', null, false, true)]],
            ],
            'externare' => [[
                'title' => 'Externare și recontrol', 'note' => 'La salvarea externării, rezumatul pacientului se trimite automat pe email. Un mesaj postoperator de follow-up este trimis automat la 48 de ore după externare.',
                'fields' => [
                    $f('data_externare', 'Data externare', 'date', null, true), $f('ora_externare', 'Ora externare', 'time', null, true),
                    $f('control_recomandat', 'Recontrol postoperator recomandat', 'radio', 'yesno'),
                    $f('recomandari_postoperatorii_text', 'Recomandări postoperatorii', 'textarea', null, false, true),
                ],
            ]],
            'validare' => [[
                'title' => 'Validare pentru Primăria Târgu Mureș',
                'fields' => [
                    $f('validat_pentru_primarie', 'Validat pentru Primărie', 'select', 'yesno', true), $f('data_validare', 'Data validare', 'date'),
                    $f('validat_de', 'Validat de', 'select', 'doctori', true), $f('observatii_validare', 'Observații validare', 'textarea', null, false, true),
                ],
            ]],
            'corectare' => [
                ['title' => 'Proprietar', 'note' => 'Datele proprietarului și CI-ul se sincronizează automat la toate animalele din aceeași vizită.', 'fields' => [
                    $f('nume_proprietar', 'Nume proprietar', 'text', null, true), $f('telefon', 'Telefon', 'tel', null, true), $f('email', 'Email', 'email'),
                    $f('adresa_ci', 'Adresă CI', 'textarea'), $f('serie_numar_ci', 'Serie / număr CI'), $f('localitate', 'Localitate'),
                ]],
                ['title' => 'Pacient', 'fields' => [
                    $f('nume_pacient', 'Nume pacient', 'text', null, true), $f('specie', 'Specie', 'select', 'specie', true), $f('sex', 'Sex', 'select', 'sex', true),
                    $f('rasa', 'Rasă', 'text', null, true), $f('culoare_semnalmente', 'Culoare / semnalmente'), $f('varsta_estimata', 'Vârstă estimată'),
                    $f('greutate_reala', 'Greutate reală (kg)'), $f('microcip', 'Microcip'), $f('vaccinat', 'Vaccinat', 'select', 'yesno'), $f('data_vaccinare', 'Data vaccinării', 'date'),
                ]],
                ['title' => 'Programare și intervenție', 'fields' => [
                    $f('data_programare', 'Data programării', 'date'), $f('ora_programare', 'Ora programării', 'time'), $f('procedura_programata', 'Procedură programată', 'select', 'procedura'),
                    $f('data_interventie', 'Data intervenției', 'date'), $f('procedura_efectuata', 'Procedură efectuată'), $f('tip_anestezie', 'Tip anestezie', 'select', 'anestezie'),
                    $f('medic_operator', 'Medic operator', 'select', 'doctori'), $f('tehnician_asistent', 'Asistent', 'select', 'asistenti'),
                ]],
                ['title' => 'Document identitate', 'fields' => [
                    $f('documente_scanate', 'Înlocuiește documentul scanat', 'file_ci', null, false, true), $f('ci_verificat', 'CI verificat', 'select', 'yesno'),
                    $f('observatii_documente', 'Observații documente', 'textarea', null, false, true),
                ]],
                ['title' => 'Fotografie pacient', 'note' => 'Corectare număr: scrie aici exact numărul care se vede pe fotografia pacientului. O modificare manuală are prioritate și va fi păstrată.', 'fields' => [
                    $f('numar_pacient', 'Număr pacient'), $f('poza_pacient', 'Înlocuiește fotografia', 'file_photo', null, false, true),
                ]],
            ],
            default => [],
        };
    }

    /** Optiunile unui camp: o lista din CaseFields::choices(), lista de medici/asistenti din setari, sau o lista data. */
    public static function options(string|array|null $options): array
    {
        if (is_array($options)) {
            return $options;
        }

        return match ($options) {
            null => [],
            'doctori', 'asistenti' => CaseFields::staffList($options),
            default => CaseFields::choices()[$options] ?? [],
        };
    }
}
