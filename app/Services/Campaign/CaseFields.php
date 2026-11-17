<?php

namespace App\Services\Campaign;

use App\Support\Settings;
use Carbon\Carbon;

/**
 * Campurile dosarului de sterilizare, cu aceleasi nume ca tabela hv_st_cases din pluginul WordPress 2.7.6
 * (defaults(), choices(), text_fields() ... din plugin). Pastrand numele, dosarele vechi si cele noi au
 * aceeasi forma, iar rapoartele si exporturile functioneaza identic pentru ambele.
 */
final class CaseFields
{
    public const CITY_BULK_VALIDATOR = 'Dr. Festeu Radu';

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'cod_caz' => '', 'data_programare' => '', 'ora_programare' => '', 'initial_appointment_id' => 0, 'initial_appointment_synced_at' => '',
            'visit_group_id' => 0, 'shared_consent_id' => 0, 'data_prezentare' => '', 'nume_proprietar' => '', 'telefon' => '', 'email' => '',
            'adresa_ci' => '', 'serie_numar_ci' => '', 'localitate' => 'Targu Mures', 'specie' => '', 'sex' => '', 'nume_pacient' => '', 'rasa' => '',
            'culoare_semnalmente' => '', 'varsta_estimata' => '', 'greutate_estimativa' => '', 'animal_cu_stapan' => '', 'microcip' => '',
            'carnet_sanatate' => '', 'vaccinat' => '', 'data_vaccinare' => '', 'observatii_initiale' => '', 'status_caz' => 'Programat', 'procedura_programata' => '',

            'greutate_reala' => '', 'temperatura' => '', 'pacient_apt' => '', 'motiv_amanare' => '', 'consimtamant_semnat' => '', 'gdpr_semnat' => '',
            'analize_acceptate' => '', 'analize_efectuate' => '', 'inhalatorie_acceptata' => '', 'risc_anestezic' => '', 'observatii_preoperatorii' => '',
            'comportament_neobisnuit' => '', 'comportament_detalii' => '', 'deparazitat' => '', 'deparazitare_detalii' => '', 'diaree_voma' => '',
            'diaree_voma_detalii' => '', 'tip_hrana_select' => '', 'tip_hrana_detalii' => '', 'frecventa_respiratorie' => '', 'frecventa_cardiaca' => '',
            'culoare_mucoase' => '', 'istoric_medical' => '', 'mentiuni_consult_preoperatorii' => '', 'completat_preoperator_de' => '',
            'timestamp_preoperator' => '', 'status_preoperator' => '',

            'data_interventie' => '', 'data_raportare_primarie' => '', 'ora_start' => '', 'ora_final' => '', 'procedura_efectuata' => '', 'tip_anestezie' => '',
            'protocol_anestezic' => '', 'substanta1' => '', 'doza1' => '', 'substanta2' => '', 'doza2' => '', 'substanta3' => '', 'doza3' => '',
            'alte_substante' => '', 'complicatii' => '', 'mentiuni_medicale' => '', 'mentiuni_intra_postoperatorii' => '', 'medic_operator' => '',
            'tehnician_asistent' => '', 'status_postoperator' => '', 'poza_pacient' => '', 'numar_pacient' => '', 'numar_pacient_vechi' => '',
            'numar_pacient_auto_at' => '', 'numar_pacient_raport' => '', 'numar_pacient_raport_sursa' => '', 'numar_pacient_raport_confirmat_at' => '',
            'numar_pacient_foto_confirmat' => '', 'numar_pacient_foto_verificat_de' => '', 'numar_pacient_foto_verificat_at' => '',
            'photo_upload_token' => '', 'photo_uploaded_at' => '', 'photo_upload_source' => '', 'completat_interventie_de' => '',
            'timestamp_interventie' => '', 'status_interventie' => '',

            'consimtamant_acceptare_proceduri' => '', 'document_gdpr' => '', 'ci_verificat' => '', 'carnet_sau_fisa' => '', 'documente_scanate' => '',
            'observatii_documente' => '', 'completat_documente_de' => '', 'timestamp_documente' => '', 'status_documente' => '',

            'data_externare' => '', 'ora_externare' => '', 'recomandari_postoperatorii' => '', 'pachet_postoperator' => '', 'control_recomandat' => '',
            'data_control' => '', 'followup_efectuat' => '', 'data_followup' => '', 'evolutie_comunicat' => '', 'observatii_externare' => '',
            'recomandari_postoperatorii_text' => '', 'completat_externare_de' => '', 'timestamp_externare' => '', 'status_externare' => '',
            'postop_email_due_at' => '', 'postop_email_sent_at' => '', 'postop_email_status' => '', 'postop_email_error' => '', 'postop_email_manual_at' => '',
            'final_summary_email_sent_at' => '', 'final_summary_email_status' => '', 'final_summary_email_error' => '', 'consent_id' => 0, 'consent_synced_at' => '',

            'date_proprietar_complete' => '', 'date_pacient_complete' => '', 'consimtamant_complet' => '', 'gdpr_complet' => '', 'anestezie_completata' => '',
            'substante_completate' => '', 'medic_operator_completat' => '', 'poza_incarcata' => '', 'complicatii_mentiuni_completate' => '',
            'validat_pentru_primarie' => 'Nu', 'validat_de' => '', 'data_validare' => '', 'observatii_validare' => '',

            'nr_fisa' => '', 'data_fisa' => '', 'cod_identificare' => '', 'sterilizat' => '', 'contact_persoana' => '', 'contact_telefon' => '',
            'contact_email' => '', 'clinica_curenta' => 'Clinica Veterinara HikeVet', 'circuit_doctor' => '', 'circuit_doctor_tel' => '', 'preluare_triaj' => '',
            'triaj_doctor' => '', 'triaj_data' => '', 'triaj_ora' => '', 'trimitere_programare' => '', 'trimitere_doctor' => '', 'trimitere_data' => '',
            'trimitere_ora' => '', 'provenienta_anul' => '', 'alte_animale_acasa' => '', 'habitat' => '', 'calatorii' => '', 'calatorii_data' => '',
            'capuse' => '', 'capuse_data' => '', 'dieta_tip' => '', 'dieta_cantitate' => '', 'dieta_administrare' => '', 'vaccinari_la_zi' => '',
            'vaccinari_data' => '', 'deparazitari_la_zi' => '', 'deparazitari_data' => '', 'boli_anterioare' => '', 'operatii_anterioare' => '',
            'medicatie_curenta' => '', 'suplimente' => '', 'note_fisa' => '', 'atentionari' => '', 'responsabil_caz' => '', 'dr_intern' => '', 'student' => '',
            'motiv_prezentarii' => '', 'debut_primele_semne' => '', 'frecventa_durata' => '', 'tratament_consult' => '', 'evolutie_raspuns_med' => '',
            'note_examinare' => '', 'status_fisa' => 'Necompletata', 'completat_fisa_de' => '', 'timestamp_fisa' => '',

            'creat_de' => '', 'timestamp_creat' => '', 'actualizat_de' => '', 'timestamp_actualizat' => '',
        ];
    }

    /** @return array<string,list<string>> */
    public static function choices(): array
    {
        return [
            'status_caz' => ['Programat', 'Confirmat', 'Prezentat', 'Apt interventie', 'Amanat medical', 'In interventie', 'Operat', 'Externat', 'Follow-up efectuat', 'Validat primarie', 'Incomplet', 'Neprezentat'],
            'specie' => ['Caine', 'Pisica'],
            'sex' => ['Mascul', 'Femela'],
            'yesno' => ['Da', 'Nu'],
            'stapan' => ['Cu stapan', 'Fara stapan'],
            'procedura' => ['Sterilizare pisică', 'Sterilizare câine', 'Castrare motan', 'Castrare câine'],
            'procedura_medicala' => ['OVH catea', 'OVH pisica', 'OH caine', 'OH motan'],
            'anestezie' => ['Injectabila', 'Inhalatorie'],
            'doctori' => ['Dr. Festeu Radu', 'Dr. Dregics Szidonia', 'Dr. Anca Ion'],
            'asistenti' => ['Asist. Lorena Pop-Suci', 'Asist. Andrea Oroian', 'Asist. Tamas Cseh', 'Asist. Floriana Sopterean'],
            'hrana' => ['Industriala', 'De casa'],
            'status_preop' => ['Necompletat', 'Completat', 'Apt interventie', 'Amanat medical'],
            'status_externare' => ['Necompletat', 'Externat', 'Follow-up de efectuat', 'Follow-up efectuat'],
            'habitat' => ['In casa', 'Afara', 'Mixt'],
            'appointment_type' => ['Castrare caine', 'Castrare motan', 'Sterilizare pisica', 'Sterilizare catea'],
            'appointment_status' => ['Programat', 'Confirmat', 'Prezentat', 'Finalizat', 'Neprezentat', 'Anulat'],
            'reminder_days' => ['0', '1', '2', '3', '7'],
        ];
    }

    /** Listele de medici si asistenti sunt administrabile din setari; valorile din plugin sunt cele implicite. */
    public static function staffList(string $key): array
    {
        $configured = Settings::get('campaign.'.$key);
        $list = is_array($configured) ? $configured : array_filter(array_map('trim', explode("\n", (string) $configured)));

        return $list ?: self::choices()[$key];
    }

    public static function textFields(): array
    {
        return ['cod_caz', 'nume_proprietar', 'telefon', 'email', 'adresa_ci', 'serie_numar_ci', 'localitate', 'specie', 'sex', 'nume_pacient', 'rasa', 'culoare_semnalmente',
            'varsta_estimata', 'animal_cu_stapan', 'microcip', 'carnet_sanatate', 'vaccinat', 'status_caz', 'procedura_programata', 'pacient_apt', 'consimtamant_semnat',
            'gdpr_semnat', 'analize_acceptate', 'analize_efectuate', 'inhalatorie_acceptata', 'risc_anestezic', 'status_preoperator', 'responsabil_caz',
            'comportament_neobisnuit', 'deparazitat', 'diaree_voma', 'tip_hrana_select', 'frecventa_respiratorie', 'frecventa_cardiaca', 'culoare_mucoase',
            'procedura_efectuata', 'tip_anestezie', 'medic_operator', 'tehnician_asistent', 'status_postoperator', 'status_interventie', 'numar_pacient',
            'consimtamant_acceptare_proceduri', 'document_gdpr', 'ci_verificat', 'carnet_sau_fisa', 'status_documente', 'recomandari_postoperatorii',
            'pachet_postoperator', 'control_recomandat', 'followup_efectuat', 'status_externare', 'validat_pentru_primarie', 'validat_de',
            'substanta1', 'doza1', 'substanta2', 'doza2', 'substanta3', 'doza3', 'nr_fisa', 'cod_identificare', 'sterilizat', 'contact_persoana',
            'contact_telefon', 'contact_email', 'clinica_curenta', 'circuit_doctor', 'circuit_doctor_tel', 'preluare_triaj', 'triaj_doctor',
            'trimitere_doctor', 'habitat', 'calatorii', 'capuse', 'vaccinari_la_zi', 'deparazitari_la_zi', 'suplimente', 'status_fisa'];
    }

    public static function textareaFields(): array
    {
        return ['observatii_initiale', 'motiv_amanare', 'observatii_preoperatorii', 'comportament_detalii', 'deparazitare_detalii', 'diaree_voma_detalii',
            'tip_hrana_detalii', 'istoric_medical', 'mentiuni_consult_preoperatorii', 'protocol_anestezic', 'alte_substante', 'complicatii',
            'mentiuni_medicale', 'mentiuni_intra_postoperatorii', 'observatii_documente', 'evolutie_comunicat', 'observatii_externare',
            'recomandari_postoperatorii_text', 'observatii_validare', 'trimitere_programare', 'provenienta_anul', 'alte_animale_acasa', 'dieta_tip',
            'dieta_cantitate', 'dieta_administrare', 'boli_anterioare', 'operatii_anterioare', 'medicatie_curenta', 'note_fisa', 'atentionari',
            'motiv_prezentarii', 'debut_primele_semne', 'frecventa_durata', 'tratament_consult', 'evolutie_raspuns_med', 'note_examinare'];
    }

    public static function dateFields(): array
    {
        return ['data_programare', 'data_prezentare', 'data_interventie', 'data_raportare_primarie', 'data_externare', 'data_control', 'data_validare', 'data_fisa',
            'triaj_data', 'trimitere_data', 'calatorii_data', 'capuse_data', 'vaccinari_data', 'deparazitari_data', 'data_vaccinare'];
    }

    public static function timeFields(): array
    {
        return ['ora_programare', 'ora_start', 'ora_final', 'ora_externare', 'triaj_ora', 'trimitere_ora'];
    }

    public static function decimalFields(): array
    {
        return ['greutate_estimativa', 'greutate_reala', 'temperatura'];
    }

    /** Toate campurile pe care un utilizator le poate trimite dintr-un formular. */
    public static function editable(): array
    {
        return array_merge(self::textFields(), self::textareaFields(), self::dateFields(), self::timeFields(), self::decimalFields());
    }

    public static function sanitizeDate(mixed $value): ?string
    {
        $value = trim(strip_tags((string) $value));
        if ($value === '' || str_starts_with($value, '0000-00-00') || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public static function sanitizeTime(mixed $value): string
    {
        $value = trim(strip_tags((string) $value));
        if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $value, $m) && (int) $m[1] < 24 && (int) $m[2] < 60) {
            return sprintf('%02d:%02d', $m[1], $m[2]);
        }

        return '';
    }

    /** Accepta 5.5, 5,5, 5/2 si 5 1/2, ca pluginul. */
    public static function sanitizeAmount(mixed $value): ?float
    {
        $value = trim(str_replace(',', '.', strip_tags((string) $value)));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d+)\s+(\d+)\s*\/\s*(\d+)$/', $value, $m) && (int) $m[3] !== 0) {
            return (float) $m[1] + ((float) $m[2] / (float) $m[3]);
        }
        if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $value, $m) && (int) $m[2] !== 0) {
            return (float) $m[1] / (float) $m[2];
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /** Aplica valorile trimise peste dosar, cu aceeasi curatare pe tip de camp ca pluginul. */
    public static function sanitizeInto(array $raw, array $row): array
    {
        foreach (self::textFields() as $field) {
            if (array_key_exists($field, $raw)) {
                $value = trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string) $raw[$field])));
                $row[$field] = $field === 'email' ? mb_strtolower(filter_var($value, FILTER_SANITIZE_EMAIL) ?: '') : $value;
            }
        }
        foreach (self::textareaFields() as $field) {
            if (array_key_exists($field, $raw)) {
                $row[$field] = trim(strip_tags(str_replace("\r\n", "\n", (string) $raw[$field])));
            }
        }
        foreach (self::dateFields() as $field) {
            if (array_key_exists($field, $raw)) {
                $row[$field] = self::sanitizeDate($raw[$field]);
            }
        }
        foreach (self::timeFields() as $field) {
            if (array_key_exists($field, $raw)) {
                $row[$field] = self::sanitizeTime($raw[$field]);
            }
        }
        foreach (self::decimalFields() as $field) {
            if (array_key_exists($field, $raw)) {
                $row[$field] = self::sanitizeAmount($raw[$field]);
            }
        }

        return $row;
    }

    /** Dosar complet: valorile salvate peste valorile implicite; ca normalize_case_row() din plugin. */
    public static function normalize(?array $row): array
    {
        $row = array_merge(self::defaults(), array_filter((array) $row, fn ($v) => $v !== null));
        foreach (self::dateFields() as $field) {
            $row[$field] = self::sanitizeDate($row[$field]) ?? '';
        }
        if (empty($row['vaccinat']) && ! empty($row['carnet_sanatate'])) {
            $row['vaccinat'] = $row['carnet_sanatate'];
        }

        return $row;
    }

    public static function scheduledProcedure(array $row): string
    {
        $species = mb_strtolower(trim((string) ($row['specie'] ?? '')));
        $sex = mb_strtolower(trim((string) ($row['sex'] ?? '')));

        return match (true) {
            $species === 'pisica' && $sex === 'femela' => 'Sterilizare pisică',
            $species === 'caine' && $sex === 'femela' => 'Sterilizare câine',
            $species === 'pisica' && $sex === 'mascul' => 'Castrare motan',
            $species === 'caine' && $sex === 'mascul' => 'Castrare câine',
            default => '',
        };
    }

    public static function appointmentTypeFromProcedure(?string $procedure): string
    {
        $p = self::ascii(mb_strtolower(trim((string) $procedure)));

        return match (true) {
            str_contains($p, 'castrare') && str_contains($p, 'motan') => 'Castrare motan',
            str_contains($p, 'castrare') && str_contains($p, 'caine') => 'Castrare caine',
            str_contains($p, 'sterilizare') && str_contains($p, 'pis') => 'Sterilizare pisica',
            default => 'Sterilizare catea',
        };
    }

    /** @return array{specie:string,sex:string,procedura:string} */
    public static function caseFieldsForAppointmentType(string $type): array
    {
        $map = [
            'Castrare caine' => ['specie' => 'Caine', 'sex' => 'Mascul', 'procedura' => 'Castrare câine'],
            'Castrare motan' => ['specie' => 'Pisica', 'sex' => 'Mascul', 'procedura' => 'Castrare motan'],
            'Sterilizare pisica' => ['specie' => 'Pisica', 'sex' => 'Femela', 'procedura' => 'Sterilizare pisică'],
            'Sterilizare catea' => ['specie' => 'Caine', 'sex' => 'Femela', 'procedura' => 'Sterilizare câine'],
        ];

        return $map[$type] ?? $map['Sterilizare catea'];
    }

    public static function medicalProcedureFromAppointmentType(string $type): string
    {
        return ['Sterilizare catea' => 'OVH catea', 'Sterilizare pisica' => 'OVH pisica', 'Castrare caine' => 'ORH caine', 'Castrare motan' => 'ORH motan'][$type] ?? 'OVH catea';
    }

    public static function medicalProcedure(array $row): string
    {
        return self::medicalProcedureFromAppointmentType(self::appointmentTypeFromProcedure($row['procedura_programata'] ?? ''));
    }

    public static function minutes(?string $time): int
    {
        $time = self::sanitizeTime($time);
        if ($time === '') {
            return 0;
        }
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    public static function timeFromMinutes(int $minutes): string
    {
        $minutes = max(0, min(23 * 60 + 59, $minutes));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function ascii(string $value): string
    {
        return strtr($value, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T']);
    }

    /** Cheie de comparatie pentru nume: fara diacritice, majuscule, spatii sau semne (integrity_identity_key). */
    public static function identityKey(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(self::ascii(trim((string) $value))));
    }

    /** Adresa CI valida: minim 5 caractere, cu litere, si nu un CNP pus in campul adresa. */
    public static function cityAddressIsValid(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) < 5) {
            return false;
        }
        $digits = preg_replace('/\D+/', '', $value);
        if (strlen($digits) === 13 && preg_match('/^\s*\d[\d\s.\/-]*$/', $value)) {
            return false;
        }

        return (bool) preg_match('/\p{L}/u', $value);
    }

    public static function now(): string
    {
        return Carbon::now()->format('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return Carbon::now()->format('Y-m-d');
    }

    /** Textul standard al recomandarilor postoperatorii, identic cu pluginul. */
    public static function postopRecommendationsDefault(): string
    {
        return "Animalul supus anesteziei generale sau unei intervenții chirurgicale trebuie supravegheat, nu se recomandă a fi lăsat singur în următoarele 24 de ore. Vă rugăm să asigurați pentru animalul Dumneavoastră un spațiu închis, cu posibilitate de încălzire pe timpul iernii, unde va putea fi observat.\n\nLa 1-2 ore după încheierea operației/anesteziei animalul va fi înapoiat proprietarului, de obicei în stare trează. Uneori, în ziua operației/anesteziei și în ziua următoare, animalul poate manifesta o stare de somnolență și/sau incoordonare a mersului. Voma și tremuratul sunt efecte adverse ce pot apărea după anestezia generală.\n\nVă rugăm nu hrăniți și nu dați apă animalului înainte ca el să își revină complet din anestezie (aproximativ 3-4 ore). După revenire va putea fi hrănit cu porții mici de mâncare ușor digestibilă.\n\nAsigurați animalului dumneavoastră un culcuș moale și cald, nu recomandăm însă folosirea pernelor electrice sau a lămpilor încălzitoare.\n\nPlaga operatorie trebuie protejată conform indicațiilor medicului veterinar, în caz de nevoie cu un guler protector. Dacă folosiți gulerul pentru animalul dumneavoastră, asigurați-vă că acesta nu va împiedica animalul să mănânce sau să bea apă.\n\nToate animalele operate vor primi analgezice postoperator, care au efect prelungit. Administrarea analgezicelor suplimentare nu este necesară, doar în cazuri speciale veți primi tablete pentru a fi administrate acasă în zilele ce urmează.\n\nPe durata convalescenței animalul dumneavoastră poate reacționa diferit la atingere sau manipulare, uneori chiar agresiv. Vă rugăm să aveți în vedere acest lucru pentru evitarea accidentelor, în special dacă aveți copii mici în familie.\n\nÎn caz de complicații postoperatorii puteți apela numărul serviciului de urgență: 0733-449966.\n\nDacă observați oricare dintre următoarele simptome, vă rugăm să vă prezentați cât mai repede la cabinet (urgențe):\n- Sângerare abundentă de la nivelul plăgii operatorii.\n- Dacă animalul nu reacționează la chemare, atingere, are o stare comatoasă (inconștient).\n- Dacă abdomenul devine balonat și tensionat.\n\nComplicații pot apărea și la câteva zile de la operație. Recomandăm să anunțați medicul veterinar dacă apar inflamarea sau înroșirea plăgii operatorii, exudat purulent, toaletarea excesivă a plăgii, scărpinatul sau frecarea zonei operate. Uneori pot apărea reacții de hipersensibilitate individuale față de firele de sutură utilizate.\n\nO altă complicație poate fi scoaterea firelor externe de sutură de către animal, prin lins sau tragerea lor. Pentru a evita acest lucru, în caz de nevoie, se poate folosi un guler protector. Majoritatea plăgilor operatorii sunt închise în mai multe straturi; chiar dacă firele externe sunt îndepărtate, trebuie anunțat medicul veterinar cât mai repede.\n\nRecontrolul postoperator se face la cel mult 14 zile de la intervenție, firele de sutură sunt resorbabile și nu necesită îndepărtare decât în anumite cazuri mai speciale.";
    }

    public static function postopRecommendations(array $row): string
    {
        $stored = trim((string) ($row['recomandari_postoperatorii_text'] ?? ''));

        return $stored !== '' ? $stored : self::postopRecommendationsDefault();
    }
}
