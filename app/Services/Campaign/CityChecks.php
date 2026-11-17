<?php

namespace App\Services\Campaign;

use App\Enums\ConsentStatus;
use App\Models\Consent;
use App\Models\SterilizationCase;
use App\Models\SterilizationDocument;
use Illuminate\Support\Facades\DB;

/**
 * Verificarile pentru Primarie: auto_validation(), is_ready_for_city(), case_completion_checklist(),
 * consent_identity_check() si consent_gdpr_export_state() din plugin, pe datele din platforma noua.
 */
class CityChecks
{
    private const READY_FLAGS = ['date_proprietar_complete', 'adresa_ci_valida', 'ci_document_incarcat', 'ci_proprietar_consistent',
        'date_pacient_complete', 'numar_pacient_unic', 'consimtamant_complet', 'consent_identitate_corecta', 'anestezie_completata',
        'substante_completate', 'medic_operator_completat', 'poza_incarcata', 'poza_pacient_unica', 'complicatii_mentiuni_completate'];

    /** @var array<int,?Consent> */
    private array $consents = [];

    public function consentFor(array $row): ?Consent
    {
        $id = (int) (($row['consent_id'] ?? 0) ?: ($row['shared_consent_id'] ?? 0));
        if (! $id) {
            return null;
        }

        return $this->consents[$id] ??= Consent::query()->find($id);
    }

    public function forget(): void
    {
        $this->consents = [];
    }

    public function consentIsSigned(array $row): bool
    {
        return $this->consentFor($row)?->status === ConsentStatus::Signed;
    }

    /**
     * confirmed | signed_confirmation | negative | missing | unknown (consent_gdpr_export_state).
     * „signed_confirmation”: in fluxul de semnare din v1.5.2 acordul GDPR era obligatoriu; deducerea se afiseaza
     * explicit si nu se scrie niciodata inapoi in document.
     */
    public function gdprState(?Consent $consent): string
    {
        if (! $consent) {
            return 'missing';
        }
        $payload = $consent->payload ?? [];
        $has = array_key_exists('owner_final_gdpr', $payload);
        $value = $has ? $payload['owner_final_gdpr'] : null;
        if (is_string($value)) {
            $value = mb_strtolower(trim($value));
        }
        if (in_array($value, [true, 1, '1', 'da', 'yes', 'true', 'on'], true)) {
            return 'confirmed';
        }
        if (in_array($value, ['nu', 'no', 'false', 'off'], true)) {
            return 'negative';
        }
        if ($has && ! in_array($value, [false, 0, '0', '', null], true)) {
            return 'unknown';
        }
        $signed = $consent->status === ConsentStatus::Signed;
        $signatureOk = false;
        if ($signed && preg_match('#^data:image/(?:png|jpeg|jpg);base64,(.+)$#s', (string) $consent->owner_signature, $m)) {
            $bytes = base64_decode($m[1], true);
            $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
            $signatureOk = $info && in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true);
        }
        if ($signed && $signatureOk && $consent->signed_at) {
            return 'signed_confirmation';
        }

        return $has ? 'negative' : 'missing';
    }

    public static function gdprLabel(string $state): string
    {
        return [
            'confirmed' => 'CONFIRMAT',
            'signed_confirmation' => 'CONFIRMAT PRIN SEMNAREA DOCUMENTULUI (acord obligatoriu in fluxul de semnare)',
            'negative' => 'NECONFIRMAT',
            'missing' => 'DOCUMENT VECHI - confirmarea GDPR separata nu este inregistrata in datele salvate',
            'unknown' => 'DE VERIFICAT - valoarea salvata nu poate fi interpretata',
        ][$state] ?? $state;
    }

    public function gdprAccepted(array $row): bool
    {
        return $this->consentIsSigned($row) && in_array($this->gdprState($this->consentFor($row)), ['confirmed', 'signed_confirmation'], true);
    }

    /** @return array{ok:bool,owner_ok:bool,patient_ok:bool,consent_id:int,consent_owner:string,consent_patient:string} */
    public function identity(array $row): array
    {
        $result = ['ok' => false, 'owner_ok' => false, 'patient_ok' => false, 'consent_id' => 0, 'consent_owner' => '', 'consent_patient' => ''];
        $consent = $this->consentFor($row);
        $result['consent_id'] = (int) $consent?->id;
        if (! $consent || $consent->status !== ConsentStatus::Signed) {
            return $result;
        }
        $payload = $consent->payload ?? [];
        $result['consent_owner'] = trim((string) ($payload['owner_name'] ?? ''));
        $result['consent_patient'] = trim((string) ($payload['pet_name'] ?? ''));
        $ownerCase = CaseFields::identityKey($row['nume_proprietar'] ?? '');
        $result['owner_ok'] = $ownerCase !== '' && $ownerCase === CaseFields::identityKey($payload['owner_name'] ?? '');

        $patientCase = CaseFields::identityKey($row['nume_pacient'] ?? '');
        // ABATERE: pluginul compara cu lista pacientilor actualizata dupa semnare (meta hv_consent_visit_patients), asa ca
        // un pacient redenumit sau adaugat dupa semnare trecea verificarea. Aici se compara cu ce s-a semnat efectiv.
        $patients = $payload['visit_patients'] ?? [];
        if ($patients) {
            foreach ($patients as $p) {
                if (is_array($p) && CaseFields::identityKey($p['pet_name'] ?? '') === $patientCase && $patientCase !== '') {
                    $result['patient_ok'] = true;
                    break;
                }
            }
        } else {
            $result['patient_ok'] = $patientCase !== '' && $patientCase === CaseFields::identityKey($payload['pet_name'] ?? '');
        }
        $result['ok'] = $result['owner_ok'] && $result['patient_ok'];

        return $result;
    }

    /** Anul numarului de pacient: anul interventiei, altfel al programarii, altfel al fisei. */
    public static function patientNumberYear(array $row): int
    {
        foreach (['data_interventie', 'data_programare', 'data_fisa'] as $field) {
            if ($date = CaseFields::sanitizeDate($row[$field] ?? '')) {
                return (int) substr($date, 0, 4);
            }
        }

        return (int) now()->format('Y');
    }

    public static function reportNumber(array $row): string
    {
        $frozen = trim((string) ($row['numar_pacient_raport'] ?? ''));

        return $frozen !== '' ? $frozen : trim((string) ($row['numar_pacient'] ?? ''));
    }

    /** Expresia SQL portabila (MySQL si SQLite) pentru anul dosarului. */
    public static function yearSql(): string
    {
        return 'SUBSTR(COALESCE(data_interventie, data_programare, data_fisa), 1, 4)';
    }

    public function patientNumberUnique(array $row): bool
    {
        $number = self::reportNumber($row);
        $id = (int) ($row['id'] ?? 0);
        if ($number === '' || ! $id) {
            return false;
        }

        return ! SterilizationCase::query()->active()->whereKeyNot($id)
            ->whereRaw("COALESCE(NULLIF(numar_pacient_raport, ''), NULLIF(numar_pacient, '')) = ?", [$number])
            ->whereRaw(self::yearSql().' = ?', [(string) self::patientNumberYear($row)])->exists();
    }

    /** Documentul curent (neinlocuit) de un anumit tip al dosarului. */
    public function currentDocument(int $caseId, string $kind): ?SterilizationDocument
    {
        return SterilizationDocument::query()->where('sterilization_case_id', $caseId)->where('kind', $kind)
            ->whereNull('superseded_at')->latest('id')->first();
    }

    /** Fotografia nu este folosita la alt pacient (aceeasi amprenta la un pacient cu alt nume sau alt numar). */
    public function photoUnique(array $row): bool
    {
        $id = (int) ($row['id'] ?? 0);
        if (trim((string) ($row['poza_pacient'] ?? '')) === '' || ! $id) {
            return false;
        }
        $doc = $this->currentDocument($id, SterilizationDocument::PHOTO);
        if (! $doc?->sha256) {
            return true;
        }
        foreach ($this->otherCasesWithSameFile($id, SterilizationDocument::PHOTO, $doc->sha256) as $other) {
            if (CaseFields::identityKey($other['nume_pacient'] ?? '') !== CaseFields::identityKey($row['nume_pacient'] ?? '')
                || trim((string) ($other['numar_pacient'] ?? '')) !== trim((string) ($row['numar_pacient'] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    /** Acelasi CI nu apare la un proprietar cu alt nume. */
    public function idCardConsistent(array $row): bool
    {
        $id = (int) ($row['id'] ?? 0);
        if (trim((string) ($row['documente_scanate'] ?? '')) === '' || ! $id) {
            return false;
        }
        $doc = $this->currentDocument($id, SterilizationDocument::ID_CARD);
        if (! $doc?->sha256) {
            return true;
        }
        $owner = CaseFields::identityKey($row['nume_proprietar'] ?? '');
        foreach ($this->otherCasesWithSameFile($id, SterilizationDocument::ID_CARD, $doc->sha256) as $other) {
            $otherKey = CaseFields::identityKey($other['nume_proprietar'] ?? '');
            if ($owner !== '' && $otherKey !== '' && $owner !== $otherKey) {
                return false;
            }
        }

        return true;
    }

    /** @return list<array> */
    private function otherCasesWithSameFile(int $caseId, string $kind, string $sha): array
    {
        $ids = SterilizationDocument::query()->where('kind', $kind)->where('sha256', $sha)->whereNull('superseded_at')
            ->where('sterilization_case_id', '!=', $caseId)->pluck('sterilization_case_id')->unique();

        return SterilizationCase::query()->active()->whereIn('id', $ids)->get()->map->row()->all();
    }

    /** Sincronizeaza campurile de document din consimtamantul semnat (apply_consent_status). */
    public function applyConsentStatus(array $row): array
    {
        if ($this->consentIsSigned($row)) {
            $gdpr = $this->gdprAccepted($row);
            $row['consimtamant_semnat'] = 'Da';
            $row['consimtamant_acceptare_proceduri'] = 'Da';
            $row['gdpr_semnat'] = $gdpr ? 'Da' : 'Nu';
            $row['document_gdpr'] = $gdpr ? 'Da' : 'Nu';
            $row['status_documente'] = $gdpr ? 'Completat' : 'Incomplet';
        }

        return $row;
    }

    public function documentsStatus(array $row): string
    {
        if ($this->consentIsSigned($row)) {
            return $this->gdprAccepted($row) ? 'Complete' : 'Incomplet';
        }
        $consent = ($row['consimtamant_semnat'] ?? '') === 'Da' || ($row['consimtamant_acceptare_proceduri'] ?? '') === 'Da';
        $gdpr = ($row['gdpr_semnat'] ?? '') === 'Da' || ($row['document_gdpr'] ?? '') === 'Da';

        return $consent && $gdpr ? 'Complete' : 'Incomplet';
    }

    /** auto_validation(): campurile *_complet / *_completata si starile calculate pentru checklist. */
    public function autoValidation(array $row): array
    {
        $row = $this->applyConsentStatus($row);
        $yes = 'Da';
        $signed = $this->consentIsSigned($row) || (($row['consimtamant_semnat'] ?? '') === 'Da' && ($row['consimtamant_acceptare_proceduri'] ?? '') === 'Da');
        $gdpr = $this->consentIsSigned($row) ? $this->gdprAccepted($row) : (($row['gdpr_semnat'] ?? '') === 'Da' && ($row['document_gdpr'] ?? '') === 'Da');

        $row['date_proprietar_complete'] = ($row['nume_proprietar'] && $row['telefon'] && $row['adresa_ci'] && $row['localitate']) ? $yes : 'Nu';
        $row['adresa_ci_valida'] = CaseFields::cityAddressIsValid($row['adresa_ci'] ?? '') ? $yes : 'Nu';
        $row['ci_document_incarcat'] = ! empty($row['documente_scanate']) ? $yes : 'Nu';
        $row['ci_proprietar_consistent'] = (! empty($row['documente_scanate']) && $this->idCardConsistent($row)) ? $yes : 'Nu';
        $row['date_pacient_complete'] = ($row['specie'] && $row['sex'] && $row['nume_pacient'] && $row['rasa'] && ($row['greutate_reala'] ?? '') !== '' && $row['greutate_reala'] !== null) ? $yes : 'Nu';
        $row['numar_pacient_unic'] = $this->patientNumberUnique($row) ? $yes : 'Nu';
        $row['poza_pacient_unica'] = (! empty($row['poza_pacient']) && $this->photoUnique($row)) ? $yes : 'Nu';
        $row['consent_identitate_corecta'] = ($signed && $this->identity($row)['ok']) ? $yes : 'Nu';
        $row['consimtamant_complet'] = $signed ? $yes : 'Nu';
        $row['gdpr_complet'] = $gdpr ? $yes : 'Nu';
        $row['anestezie_completata'] = ! empty($row['tip_anestezie']) ? $yes : 'Nu';
        $row['substante_completate'] = ! empty($row['substanta1']) ? $yes : 'Nu';
        $row['medic_operator_completat'] = ! empty($row['medic_operator']) ? $yes : 'Nu';
        $row['poza_incarcata'] = ! empty($row['poza_pacient']) ? $yes : 'Nu';
        $row['complicatii_mentiuni_completate'] = trim((string) ($row['mentiuni_intra_postoperatorii'] ?? '')) !== '' ? $yes : 'Nu';

        return $row;
    }

    public static function isReady(array $validated): bool
    {
        foreach (self::READY_FLAGS as $flag) {
            if (($validated[$flag] ?? '') !== 'Da') {
                return false;
            }
        }

        return true;
    }

    /** Campurile calculate de mai sus care nu sunt date ale dosarului. */
    public static function computedKeys(): array
    {
        return ['adresa_ci_valida', 'ci_document_incarcat', 'ci_proprietar_consistent', 'numar_pacient_unic', 'poza_pacient_unica', 'consent_identitate_corecta'];
    }

    /** Lista de verificare a dosarului, cu aceleasi 21 de puncte ca pluginul. */
    public function checklist(array $row): array
    {
        $signed = $this->consentIsSigned($row) || (($row['consimtamant_semnat'] ?? '') === 'Da' && ($row['consimtamant_acceptare_proceduri'] ?? '') === 'Da');
        $gdpr = $this->consentIsSigned($row) ? $this->gdprAccepted($row) : (($row['gdpr_semnat'] ?? '') === 'Da' && ($row['document_gdpr'] ?? '') === 'Da');
        $filled = fn (string $f) => trim((string) ($row[$f] ?? '')) !== '';
        $checks = [
            ['nume_proprietar', 'Nume proprietar', $filled('nume_proprietar'), 'corectare'],
            ['telefon', 'Telefon proprietar', $filled('telefon'), 'corectare'],
            ['adresa_ci', 'Adresă CI', CaseFields::cityAddressIsValid($row['adresa_ci'] ?? ''), 'corectare'],
            ['localitate', 'Localitate', $filled('localitate'), 'corectare'],
            ['documente_scanate', 'Poză / scan CI proprietar', $filled('documente_scanate'), 'corectare'],
            ['ci_owner', 'CI aparține aceluiași proprietar în toate dosarele', $filled('documente_scanate') && $this->idCardConsistent($row), 'corectare', 'documente_scanate'],
            ['nume_pacient', 'Nume pacient', $filled('nume_pacient'), 'preoperator'],
            ['specie', 'Specie', $filled('specie'), 'preoperator'],
            ['sex', 'Sex', $filled('sex'), 'preoperator'],
            ['rasa', 'Rasă', $filled('rasa'), 'preoperator'],
            ['greutate_reala', 'Greutate reală', ($row['greutate_reala'] ?? '') !== '' && $row['greutate_reala'] !== null, 'preoperator'],
            ['numar_pacient', 'Număr pacient unic în anul intervenției', $this->patientNumberUnique($row), 'interventie'],
            ['consent', 'Consimțământ semnat', $signed, 'receptie'],
            ['consent_identity', 'Numele proprietarului și pacientului coincid cu consimțământul', $signed && $this->identity($row)['ok'], 'receptie', 'consent'],
            ['gdpr', 'GDPR (verificare conformitate)', $gdpr, 'receptie'],
            ['tip_anestezie', 'Tip anestezie', $filled('tip_anestezie'), 'interventie'],
            ['substanta1', 'Substanță principală', $filled('substanta1'), 'interventie'],
            ['medic_operator', 'Medic operator', $filled('medic_operator'), 'interventie'],
            ['poza_pacient', 'Fotografie pacient', $filled('poza_pacient'), 'interventie'],
            ['poza_pacient_unica', 'Fotografia nu este folosită la alt pacient', $filled('poza_pacient') && $this->photoUnique($row), 'interventie', 'poza_pacient'],
            ['mentiuni_intra_postoperatorii', 'Mențiuni intra/postoperatorii', $filled('mentiuni_intra_postoperatorii'), 'interventie'],
        ];
        $checks = array_map(fn ($c) => ['key' => $c[0], 'label' => $c[1], 'ok' => (bool) $c[2], 'tab' => $c[3], 'focus' => $c[4] ?? $c[0]], $checks);
        $done = count(array_filter($checks, fn ($c) => $c['ok']));
        $total = count($checks);

        return ['checks' => $checks, 'done' => $done, 'total' => $total, 'missing' => $total - $done,
            'percent' => (int) round($done / $total * 100), 'ready' => $done === $total];
    }

    /** Numarul de dosare din fiecare vizita (pentru filtre si lista). @return array<int,int> */
    public static function visitSizes(): array
    {
        return DB::table('sterilization_cases')->whereNull('deleted_at')
            ->selectRaw('COALESCE(NULLIF(visit_group_id, 0), id) AS gid, COUNT(*) AS n')->groupBy('gid')->pluck('n', 'gid')->map(fn ($n) => (int) $n)->all();
    }
}
