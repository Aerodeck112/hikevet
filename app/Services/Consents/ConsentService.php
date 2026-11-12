<?php

namespace App\Services\Consents;

use App\Enums\ConsentStatus;
use App\Models\Consent;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Consimtamant informat semnat pe tableta: portare a hikevet-consent-tablet 1.4.1.
 *
 * Fata de sursa, codul de 6 cifre expira, are limita de incercari si este unic printre
 * documentele in asteptare; documentul semnat primeste o amprenta SHA-256.
 */
class ConsentService
{
    public const TEMPLATE_VERSION = 'wp-consent-tablet-1.4.1';

    public const ANESTHESIA = [
        'sedare_usoara_moderata' => 'sedare usoara / moderata',
        'anestezie_generala_injectabila' => 'anestezie generala injectabila',
        'anestezie_inhalatorie' => 'anestezie inhalatorie',
        'anestezie_locala_regionala' => 'anestezie locala / regionala',
    ];

    public const INVESTIGATIONS = [
        'consult_preevaluare' => 'consult clinic si evaluare preanestezica',
        'analize_hematologice_biochimice' => 'analize hematologice si biochimice',
        'ionograma_gaze_coagulare' => 'ionograma, gaze sanguine, coagulare sau alte teste speciale',
        'ecografie' => 'ecografie',
        'radiografie' => 'radiografie simpla sau cu substanta de contrast',
        'cardiologie_ecg' => 'investigatii cardiologice / tensiune arteriala / ECG',
        'internare_monitorizare' => 'internare si monitorizare',
        'transfuzie' => 'transfuzie, daca devine necesara',
    ];

    public const MEDIA_KEYS = ['media_medical', 'media_education', 'media_publication', 'media_data_public'];

    public const FINAL_KEYS = ['owner_final_gdpr', 'owner_final_read', 'owner_final_risk', 'owner_final_lucid'];

    public const TEXT_FIELDS = ['owner_name', 'owner_phone', 'owner_address', 'owner_ci', 'owner_cnp', 'owner_email', 'pet_name',
        'pet_species_breed', 'pet_age_sex', 'pet_microchip', 'pet_weight', 'consent_date', 'procedure', 'vet_name', 'anesthesia_other',
        'food_status', 'food_detail', 'investigations_other', 'cost_from', 'cost_to', 'refused_other',
        'media_medical', 'media_education', 'media_publication', 'media_data_public'];

    /** Echivalentul sanitize_payload() (CON:186-242). */
    public function sanitize(array $raw): array
    {
        $p = ['client_user_id' => (int) ($raw['client_user_id'] ?? 0)];
        foreach (self::TEXT_FIELDS as $field) {
            $p[$field] = trim(strip_tags((string) ($raw[$field] ?? '')));
        }
        $p['consent_date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['consent_date']) && $p['consent_date'] !== '0000-00-00' ? $p['consent_date'] : now()->toDateString();
        $p['owner_email'] = filter_var($p['owner_email'], FILTER_VALIDATE_EMAIL) ? $p['owner_email'] : '';
        $p['anesthesia'] = array_values(array_intersect(array_keys(self::ANESTHESIA), (array) ($raw['anesthesia'] ?? [])));
        $p['investigations'] = array_values(array_intersect(array_keys(self::INVESTIGATIONS), (array) ($raw['investigations'] ?? [])));
        $p['refused_services'] = array_values(array_intersect(array_keys(self::INVESTIGATIONS), (array) ($raw['refused_services'] ?? [])));
        foreach (self::FINAL_KEYS as $key) {
            $p[$key] = empty($raw[$key]) ? 0 : 1;
        }
        $p['visit_group_mode'] = empty($raw['visit_group_mode']) ? 0 : 1;
        $p['visit_group_id'] = (int) ($raw['visit_group_id'] ?? 0);
        $p['visit_patients_updated_at'] = trim(strip_tags((string) ($raw['visit_patients_updated_at'] ?? '')));
        $p['visit_patients'] = [];
        foreach ((array) ($raw['visit_patients'] ?? []) as $patient) {
            if (! is_array($patient) || trim((string) ($patient['pet_name'] ?? '')) === '') {
                continue;
            }
            $row = ['case_id' => (int) ($patient['case_id'] ?? 0)];
            foreach (['case_code', 'pet_name', 'species', 'sex', 'breed', 'age', 'weight', 'microchip', 'procedure'] as $k) {
                $row[$k] = trim(strip_tags((string) ($patient[$k] ?? '')));
            }
            $p['visit_patients'][] = $row;
        }
        if (count($p['visit_patients']) > 1) {
            $p['visit_group_mode'] = 1;
        }

        return $p;
    }

    /** @return array{0: Consent, 1: string} consimtamantul si codul in clar (afisat o singura data) */
    public function create(array $raw, ?User $actor = null, string $origin = 'manual'): array
    {
        $payload = $this->sanitize($raw);
        if ($payload['anesthesia'] === [] && $payload['anesthesia_other'] === '') {
            // Din v1.5.0 a modulului, anestezia generala injectabila este selectata implicit.
            $payload['anesthesia'] = ['anestezie_generala_injectabila'];
        }

        $consent = new Consent([
            'token' => Str::random(48), 'status' => ConsentStatus::Pending, 'origin' => $origin,
            'client_id' => $payload['client_user_id'] ?: null, 'created_by' => $actor?->id, 'template_version' => self::TEMPLATE_VERSION,
        ]);
        $this->fillIndexColumns($consent, $payload);
        $consent->payload = $payload;
        $code = $this->assignCode($consent);
        $consent->save();

        Audit::log('consent.created', $consent, 'Consimțământ creat pentru '.$payload['owner_name'].' / '.$payload['pet_name'], null, null, $consent->client_id);

        return [$consent, $code];
    }

    /** Datele clinice se pot modifica doar inainte de semnarea proprietarului. */
    public function update(Consent $consent, array $raw): void
    {
        $this->assertPending($consent);
        $payload = $this->sanitize($raw);
        $this->fillIndexColumns($consent, $payload);
        $consent->payload = $payload;
        $consent->save();
    }

    public function regenerateCode(Consent $consent): string
    {
        $this->assertPending($consent);
        $code = $this->assignCode($consent);
        $consent->failed_attempts = 0;
        $consent->save();
        Audit::log('consent.code_regenerated', $consent, 'Cod nou pentru tabletă');

        return $code;
    }

    /** Cauta un consimtamant in asteptare dupa codul introdus pe tableta. */
    public function findByCode(string $code): ?Consent
    {
        $code = preg_replace('/\D+/', '', $code);
        if (strlen($code) !== 6) {
            return null;
        }

        $consent = Consent::query()->where('code_hash', $this->hash($code))->where('status', ConsentStatus::Pending)->first();
        if (! $consent || ! $consent->code_expires_at || $consent->code_expires_at->isPast()) {
            return null;
        }
        if ($consent->failed_attempts >= (int) Settings::get('consent.max_attempts', 5)) {
            return null;
        }

        return $consent;
    }

    /** Semnarea proprietarului (handle_tablet_submit, CON:1168-1215). Dupa ea documentul este blocat. */
    public function sign(Consent $consent, array $input, ?string $ip = null): Consent
    {
        return DB::transaction(function () use ($consent, $input, $ip) {
            $consent = Consent::query()->lockForUpdate()->findOrFail($consent->id);
            if ($consent->status !== ConsentStatus::Pending) {
                throw new DomainException('Acest document nu mai poate fi semnat.');
            }

            $p = $consent->payload;
            foreach (['food_status', 'food_detail', ...self::MEDIA_KEYS] as $key) {
                $p[$key] = trim(strip_tags((string) ($input[$key] ?? '')));
            }
            foreach (self::MEDIA_KEYS as $key) {
                if (! in_array($p[$key], ['da', 'nu'], true)) {
                    throw new DomainException('Selectează toate acordurile opționale.');
                }
            }
            if (! in_array($p['food_status'], ['nu_hrana', 'a_consumat_sau_nu_stiu'], true)) {
                throw new DomainException('Selectează declarația privind alimentația.');
            }
            foreach (self::FINAL_KEYS as $key) {
                $p[$key] = empty($input[$key]) ? 0 : 1;
                if (! $p[$key]) {
                    throw new DomainException($key === 'owner_final_gdpr'
                        ? 'Acordul privind prelucrarea datelor este obligatoriu pentru semnare.'
                        : 'Este necesară confirmarea finală.');
                }
            }

            $ownerSignature = $this->cleanSignature((string) ($input['owner_signature'] ?? ''));
            if (! $ownerSignature) {
                throw new DomainException('Semnătura proprietarului nu este validă.');
            }
            $hasRefused = $p['refused_services'] !== [] || $p['refused_other'] !== '';
            $refusalSignature = $this->cleanSignature((string) ($input['refusal_signature'] ?? ''));
            if ($hasRefused && ! $refusalSignature) {
                throw new DomainException('Este necesară semnătura pentru refuz informat.');
            }

            $signedAt = now();
            $consent->payload = $p;
            $consent->owner_signature = $ownerSignature;
            $consent->refusal_signature = $refusalSignature ?: null;
            $consent->signed_at = $signedAt;
            $consent->signed_ip = $ip;
            $consent->status = ConsentStatus::Signed;
            $consent->code_hash = null;
            $consent->code_expires_at = null;
            $consent->document_sha256 = $this->fingerprint($p, $ownerSignature, $refusalSignature, $signedAt->toIso8601String());
            $consent->save();

            Audit::log('consent.signed', $consent, 'Consimțământ semnat de proprietar pe tabletă', null, null, $consent->client_id);

            return $consent;
        });
    }

    /** Semnatura medicului se poate adauga sau reface si dupa semnarea proprietarului, fara a atinge restul documentului. */
    public function doctorSign(Consent $consent, string $signature, User $actor): void
    {
        if ($consent->status === ConsentStatus::Cancelled) {
            throw new DomainException('Documentul este anulat.');
        }
        $clean = $this->cleanSignature($signature);
        if (! $clean) {
            throw new DomainException('Semnătura medicului nu este validă.');
        }

        $consent->forceFill(['doctor_signature' => $clean, 'doctor_signed_at' => now(), 'doctor_signed_by' => $actor->id])->save();
        Audit::log('consent.doctor_signed', $consent, 'Semnătura medicului salvată de '.$actor->name);
    }

    public function cancel(Consent $consent, User $actor): void
    {
        $this->assertPending($consent);
        $consent->forceFill(['status' => ConsentStatus::Cancelled, 'code_hash' => null])->save();
        Audit::log('consent.cancelled', $consent, 'Consimțământ anulat de '.$actor->name);
    }

    /** Un document semnat nu se rescrie: se emite unul nou, de resemnat, iar cel vechi ramane pentru audit. */
    public function supersede(Consent $signed, array $raw, User $actor): array
    {
        if ($signed->status !== ConsentStatus::Signed) {
            throw new DomainException('Doar un document semnat poate fi înlocuit cu o versiune corectată.');
        }
        [$new, $code] = $this->create($raw, $actor, $signed->origin);
        $new->forceFill(['supersedes_id' => $signed->id, 'sterilization_case_id' => $signed->sterilization_case_id])->save();
        Audit::log('consent.superseded', $signed, 'Emisă versiune corectată: consimțământ #'.$new->id);

        return [$new, $code];
    }

    /** Verifica daca documentul semnat mai corespunde amprentei de la semnare. */
    public function verifyIntegrity(Consent $consent): bool
    {
        if (! $consent->isSigned() || ! $consent->document_sha256) {
            return false;
        }

        return hash_equals($consent->document_sha256, $this->fingerprint($consent->payload, (string) $consent->owner_signature,
            (string) $consent->refusal_signature, $consent->signed_at->toIso8601String()));
    }

    public function registerFailedAttempt(Consent $consent): void
    {
        Consent::query()->whereKey($consent->id)->increment('failed_attempts');
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function fingerprint(array $payload, string $ownerSignature, string $refusalSignature, string $signedAt): string
    {
        ksort($payload);

        return hash('sha256', json_encode([$payload, hash('sha256', $ownerSignature), hash('sha256', $refusalSignature), $signedAt], JSON_UNESCAPED_UNICODE));
    }

    private function assignCode(Consent $consent): string
    {
        do {
            $code = (string) random_int(100000, 999999);
            $hash = $this->hash($code);
        } while (Consent::query()->where('code_hash', $hash)->where('status', ConsentStatus::Pending)->whereKeyNot($consent->id ?? 0)->exists());

        $consent->code_hash = $hash;
        $consent->code_expires_at = now()->addHours((int) Settings::get('consent.code_ttl_hours', 12));

        return $code;
    }

    private function fillIndexColumns(Consent $consent, array $payload): void
    {
        $consent->owner_name = $payload['owner_name'] ?: null;
        $consent->pet_name = $payload['visit_group_mode'] && $payload['visit_patients']
            ? implode(', ', array_column($payload['visit_patients'], 'pet_name'))
            : ($payload['pet_name'] ?: null);
        $consent->procedure = $payload['procedure'] ?: null;
        $consent->vet_name = $payload['vet_name'] ?: null;
        $consent->consent_date = $payload['consent_date'];
    }

    private function cleanSignature(string $data): string
    {
        if (! preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $data) || strlen($data) > 1_400_000) {
            return '';
        }
        $binary = base64_decode(substr($data, 22), true);
        $info = $binary ? @getimagesizefromstring($binary) : false;

        return ($info && $info[2] === IMAGETYPE_PNG) ? $data : '';
    }

    private function assertPending(Consent $consent): void
    {
        if ($consent->status !== ConsentStatus::Pending) {
            throw new DomainException('Documentul este blocat: a fost deja semnat sau anulat.');
        }
    }
}
