<?php

namespace App\Services\Campaign;

use App\Enums\ConsentStatus;
use App\Models\Consent;
use App\Models\SterilizationCase;
use App\Models\User;
use App\Services\Consents\ConsentService;
use App\Support\Audit;

/**
 * Consimtamantul legat de dosar (sync_consent_for_case, consent_payload_from_case, create_corrected_consent_for_case
 * din plugin): creat automat cand dosarul are datele necesare, actualizat cat timp nu este semnat, comun pentru
 * toti pacientii unei vizite. Un document semnat nu se rescrie; daca identitatea din dosar se schimba dupa semnare
 * se emite o versiune corectata, de semnat din nou, iar originalul ramane.
 */
class CampaignConsents
{
    /** Ultimul cod de tableta generat in aceasta cerere, ca sa poata fi afisat o data utilizatorului. */
    public ?string $lastCode = null;

    public function __construct(private readonly ConsentService $consents, private readonly CityChecks $checks) {}

    /** Datele pacientului se completeaza la consult, iar datele legale ale proprietarului la receptie. */
    public static function readyForConsent(array $row): bool
    {
        foreach (['nume_proprietar', 'telefon', 'email', 'adresa_ci', 'serie_numar_ci', 'localitate', 'nume_pacient', 'specie', 'sex', 'rasa', 'greutate_reala'] as $field) {
            if (trim((string) ($row[$field] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    public static function procedureFromCase(array $row): string
    {
        return trim((string) ($row['procedura_efectuata'] ?? '')) ?: (trim((string) ($row['procedura_programata'] ?? '')) ?: CaseFields::scheduledProcedure($row));
    }

    public static function consentDate(array $row): string
    {
        foreach (['data_prezentare', 'data_interventie', 'data_programare'] as $field) {
            if ($date = CaseFields::sanitizeDate($row[$field] ?? '')) {
                return $date;
            }
        }

        return CaseFields::today();
    }

    /** @param list<array> $groupRows */
    public static function visitPatients(array $groupRows): array
    {
        return array_map(fn (array $item) => [
            'case_id' => (int) ($item['id'] ?? 0), 'case_code' => (string) ($item['cod_caz'] ?? ''), 'pet_name' => (string) ($item['nume_pacient'] ?? ''),
            'species' => (string) ($item['specie'] ?? ''), 'sex' => (string) ($item['sex'] ?? ''), 'breed' => (string) ($item['rasa'] ?? ''),
            'age' => (string) ($item['varsta_estimata'] ?? ''),
            'weight' => trim((string) ((($item['greutate_reala'] ?? '') !== '' && $item['greutate_reala'] !== null) ? $item['greutate_reala'] : ($item['greutate_estimativa'] ?? ''))),
            'microchip' => (string) ($item['microcip'] ?? ''),
            'procedure' => (string) (($item['procedura_efectuata'] ?? '') ?: ($item['procedura_programata'] ?? '')),
        ], array_values($groupRows));
    }

    /** @param list<array> $groupRows */
    public static function payloadFromCase(array $row, array $groupRows): array
    {
        $breed = trim((string) ($row['rasa'] ?? ''));
        $sex = trim((string) ($row['sex'] ?? ''));
        $age = trim((string) ($row['varsta_estimata'] ?? ''));
        $weight = trim((string) ($row['greutate_reala'] ?? ''));
        if ($weight === '') {
            $weight = trim((string) ($row['greutate_estimativa'] ?? ''));
        }
        $payload = [
            'client_user_id' => 0, 'owner_name' => (string) $row['nume_proprietar'], 'owner_phone' => (string) $row['telefon'],
            'owner_address' => (string) $row['adresa_ci'], 'owner_ci' => (string) $row['serie_numar_ci'], 'owner_cnp' => '',
            'owner_email' => (string) $row['email'], 'pet_name' => (string) $row['nume_pacient'],
            'pet_species_breed' => trim($row['specie'].($breed !== '' ? ' / '.$breed : '')),
            'pet_age_sex' => trim($age.($sex !== '' ? ($age !== '' ? ' / ' : '').$sex : '')),
            'pet_microchip' => (string) $row['microcip'], 'pet_weight' => $weight !== '' ? $weight.' kg' : '',
            'consent_date' => self::consentDate($row), 'procedure' => self::procedureFromCase($row),
            'vet_name' => (string) (($row['medic_operator'] ?? '') ?: ($row['responsabil_caz'] ?? '')),
            // Implicit anestezie generala injectabila; inhalatorie doar daca dosarul o cere explicit.
            'anesthesia' => str_contains(mb_strtolower(CaseFields::ascii((string) ($row['tip_anestezie'] ?? ''))), 'inhal') ? ['anestezie_inhalatorie'] : ['anestezie_generala_injectabila'],
            'anesthesia_other' => '', 'food_status' => '', 'food_detail' => '', 'investigations' => ['consult_preevaluare'], 'investigations_other' => '',
            'cost_from' => '', 'cost_to' => '', 'refused_services' => [], 'refused_other' => '',
            'media_medical' => '', 'media_education' => '', 'media_publication' => '', 'media_data_public' => '',
            'owner_final_read' => 0, 'owner_final_risk' => 0, 'owner_final_gdpr' => 0, 'owner_final_lucid' => 0,
            'visit_group_mode' => 0, 'visit_group_id' => 0, 'visit_patients' => [],
        ];
        if (count($groupRows) > 1) {
            $payload['visit_group_mode'] = 1;
            $payload['visit_group_id'] = (int) (($row['visit_group_id'] ?? 0) ?: $row['id']);
            $payload['visit_patients'] = self::visitPatients($groupRows);
            $payload['pet_name'] = implode(', ', array_filter(array_column($payload['visit_patients'], 'pet_name')));
            $payload['procedure'] = 'Procedurile medicale / operatorii recomandate pentru pacienții incluși în aceeași vizită';
        }

        return $payload;
    }

    /**
     * Creeaza sau actualizeaza consimtamantul dosarului. Intoarce ID-ul consimtamantului sau 0 daca dosarul nu
     * are inca datele necesare. Seteaza consent_id / shared_consent_id in $row si in toata vizita.
     *
     * @param  list<array>  $groupRows
     */
    public function sync(array &$row, array $groupRows, ?User $actor): int
    {
        $caseId = (int) ($row['id'] ?? 0);
        if (! $caseId || trim((string) $row['nume_proprietar']) === '' || trim((string) $row['nume_pacient']) === '') {
            return 0;
        }
        $consent = $this->checks->consentFor($row);
        if (! $consent) {
            foreach ($groupRows as $g) {
                if ($consent = $this->checks->consentFor($g)) {
                    break;
                }
            }
        }
        $consent ??= Consent::query()->where('sterilization_case_id', $caseId)->whereNull('superseded_by_id')->latest('id')->first();
        if ($consent && $consent->status === ConsentStatus::Cancelled) {
            $consent = null;
        }
        if (! $consent && ! self::readyForConsent($row)) {
            return 0;
        }

        if ($consent && $consent->status === ConsentStatus::Signed && ! $this->checks->identity($row)['ok']) {
            return $this->createCorrected($row, $groupRows, 'Generat automat deoarece identitatea din dosar a fost corectată după semnare', $actor);
        }

        $payload = self::payloadFromCase($row, $groupRows);
        if (! $consent) {
            [$consent, $this->lastCode] = $this->consents->create($payload, $actor, 'sterilization');
            $consent->forceFill(['sterilization_case_id' => $caseId])->save();
        } elseif ($consent->status === ConsentStatus::Pending) {
            $this->consents->update($consent, $payload);
            $consent->forceFill(['sterilization_case_id' => $caseId])->save();
        }
        $this->syncVisitPatients($consent, $groupRows ?: [$row]);
        $row['consent_id'] = $row['shared_consent_id'] = (int) $consent->id;
        $row['consent_synced_at'] = CaseFields::now();
        $this->checks->forget();

        return (int) $consent->id;
    }

    /** @param list<array> $groupRows */
    public function createCorrected(array &$row, array $groupRows, string $reason, ?User $actor): int
    {
        $old = $this->checks->consentFor($row);
        $payload = self::payloadFromCase($row, $groupRows);
        [$new, $this->lastCode] = $this->consents->create($payload, $actor, 'sterilization');
        $new->forceFill(['sterilization_case_id' => (int) $row['id'], 'supersedes_id' => $old?->id, 'correction_reason' => mb_substr($reason, 0, 250)])->save();
        if ($old) {
            $old->forceFill(['superseded_by_id' => $new->id])->save();
            Audit::log('consent.superseded', $old, 'Versiune corectată pentru dosarul '.$row['cod_caz'].': consimțământ #'.$new->id.' ('.$reason.')');
        }
        $this->syncVisitPatients($new, $groupRows ?: [$row]);
        $row['consent_id'] = $row['shared_consent_id'] = (int) $new->id;
        $row['consent_synced_at'] = CaseFields::now();
        $this->checks->forget();

        return (int) $new->id;
    }

    /** Lista pacientilor vizitei, tinuta separat de documentul semnat. @param list<array> $groupRows */
    public function syncVisitPatients(Consent $consent, array $groupRows): void
    {
        $first = $groupRows[0] ?? [];
        $consent->forceFill(['visit_patients' => self::visitPatients($groupRows), 'visit_patients_updated_at' => now(),
            'visit_group_id' => (int) (($first['visit_group_id'] ?? 0) ?: ($first['id'] ?? 0)) ?: null])->save();
    }

    /** Proprietarul s-a schimbat in dosar: documentul nesemnat primeste noile date (sync_visit_owner_from_case). */
    public function syncOwner(Consent $consent, array $row): void
    {
        if ($consent->status !== ConsentStatus::Pending) {
            return;
        }
        $payload = $consent->payload ?? [];
        $payload['owner_name'] = (string) $row['nume_proprietar'];
        $payload['owner_phone'] = (string) $row['telefon'];
        $payload['owner_email'] = (string) $row['email'];
        $payload['owner_address'] = (string) $row['adresa_ci'];
        $payload['owner_ci'] = (string) $row['serie_numar_ci'];
        $this->consents->update($consent, $payload);
    }

    public function regenerateCode(Consent $consent): string
    {
        return $this->lastCode = $this->consents->regenerateCode($consent);
    }

    public static function caseOf(Consent $consent): ?SterilizationCase
    {
        return $consent->sterilization_case_id ? SterilizationCase::query()->find($consent->sterilization_case_id) : null;
    }
}
