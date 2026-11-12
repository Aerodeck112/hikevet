<?php

namespace App\Services\Import;

use App\Enums\BalanceType;
use App\Enums\Role;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Consents\ConsentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import pentru conturi, animale, contracte, registrul Wallet, tratamente si consimtaminte.
 * Idempotent: fiecare entitate are cheia originala WordPress (wp_user_id, wp_post_id, wp_tx_id...).
 * Timestampurile originale se pastreaza; parolele raman ca hash WordPress pana la primul login.
 */
class WordPressCoreImporter
{
    public const KIND = 'wordpress_core';

    public function __construct(private readonly WordPressSource $source) {}

    public function run(bool $dryRun = true, ?int $userId = null): ImportBatch
    {
        $batch = ImportBatch::create(['kind' => self::KIND, 'source_label' => $this->source->prefix().'*', 'dry_run' => $dryRun,
            'status' => 'running', 'started_by' => $userId, 'started_at' => now()]);
        $counts = [];
        $errors = [];

        $work = function () use (&$counts, &$errors, $dryRun) {
            $userMap = $this->users($dryRun, $counts, $errors);
            $this->pets($dryRun, $counts, $userMap);
            $this->contracts($dryRun, $counts, $errors, $userMap);
            $this->wallet($dryRun, $counts, $errors, $userMap);
            $this->treatments($dryRun, $counts, $userMap);
            $this->consents($dryRun, $counts, $userMap);
        };

        try {
            $dryRun ? $work() : DB::transaction($work);
            $status = 'finished';
        } catch (\Throwable $e) {
            report($e);
            $errors[] = ['error' => 'Importul a fost oprit și anulat: '.mb_substr($e->getMessage(), 0, 500)];
            $status = 'failed';
        }

        $batch->update(['status' => $status, 'counts' => $counts, 'errors' => array_slice($errors, 0, 500), 'finished_at' => now()]);

        return $batch;
    }

    /** Clientii sunt utilizatorii cu rol hikevet_client sau cu animale / contracte in usermeta. @return array<int,int> wp id => id nou */
    private function users(bool $dryRun, array &$counts, array &$errors): array
    {
        $counts += ['users_in_source' => 0, 'users_new' => 0, 'users_existing' => 0];
        $map = User::query()->whereNotNull('wp_user_id')->pluck('id', 'wp_user_id')->all();
        if (! $this->source->has('users')) {
            return $map;
        }

        $prefix = $this->source->prefix();
        $clientIds = $this->source->table('usermeta')->where(fn ($q) => $q->whereIn('meta_key', ['hv_pets', 'hv_contracts'])
            ->orWhere(fn ($w) => $w->where('meta_key', $prefix.'capabilities')->where('meta_value', 'like', '%hikevet_client%')))->distinct()->pluck('user_id');

        foreach ($this->source->table('users')->whereIn('ID', $clientIds)->orderBy('ID')->cursor() as $row) {
            $counts['users_in_source']++;
            $email = mb_strtolower(trim((string) $row->user_email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['wp_user_id' => (int) $row->ID, 'error' => 'Email invalid; contul nu a fost importat.'];

                continue;
            }
            $existing = User::query()->where('wp_user_id', $row->ID)->orWhere('email', $email)->first();
            $counts[$existing ? 'users_existing' : 'users_new']++;
            if ($dryRun) {
                if ($existing) {
                    $map[(int) $row->ID] = $existing->id;
                }

                continue;
            }

            $meta = $this->source->table('usermeta')->where('user_id', $row->ID)->whereIn('meta_key', ['billing_phone', 'phone', 'billing_address_1', 'address', 'billing_city'])->pluck('meta_value', 'meta_key');
            $user = $existing ?? new User;
            if (! $existing) {
                $user->forceFill(['email' => $email, 'password' => Str::password(40), 'must_set_password' => false, 'wp_password_hash' => (string) $row->user_pass,
                    'created_at' => WordPressSource::date($row->user_registered) ?? now()]);
            }
            $user->forceFill(['wp_user_id' => (int) $row->ID, 'name' => trim((string) $row->display_name) ?: Str::before($email, '@'),
                'phone' => $user->phone ?: ($meta['billing_phone'] ?? $meta['phone'] ?? null), 'address' => $user->address ?: ($meta['billing_address_1'] ?? $meta['address'] ?? null),
                'city' => $user->city ?: ($meta['billing_city'] ?? null)])->save();
            if (! $user->hasRole(Role::Client->value)) {
                $user->assignRole(Role::Client->value);
            }
            $map[(int) $row->ID] = $user->id;
        }

        return $map;
    }

    private function pets(bool $dryRun, array &$counts, array $userMap): void
    {
        $counts += ['pets_in_source' => 0, 'pets_imported' => 0, 'pets_archived' => 0];
        if (! $this->source->has('usermeta')) {
            return;
        }

        foreach ($this->source->table('usermeta')->where('meta_key', 'hv_pets')->cursor() as $meta) {
            $pets = WordPressSource::meta($meta->meta_value);
            foreach (is_array($pets) ? $pets : [] as $i => $pet) {
                if (! is_array($pet) || trim((string) ($pet['pet_name'] ?? '')) === '') {
                    continue;
                }
                $counts['pets_in_source']++;
                $counts['pets_archived'] += empty($pet['archived_at']) ? 0 : 1;
                $ownerId = $userMap[(int) $meta->user_id] ?? null;
                if ($dryRun || ! $ownerId) {
                    continue;
                }
                $weight = str_replace(',', '.', (string) ($pet['weight'] ?? ''));
                DB::table('pets')->updateOrInsert(['user_id' => $ownerId, 'wp_pet_id' => (int) ($pet['id'] ?? $i + 1)], [
                    'name' => (string) $pet['pet_name'], 'species' => (string) ($pet['species'] ?? ''), 'sex' => ($pet['sex'] ?? '') ?: null,
                    'date_of_birth' => WordPressSource::date($pet['dob'] ?? ''), 'breed' => ($pet['breed'] ?? '') ?: null,
                    'weight_kg' => is_numeric($weight) ? (float) $weight : null, 'microchip' => ($pet['microchip'] ?? '') ?: null,
                    'sterilized' => ($pet['sterilized'] ?? '') ?: null, 'tier_label' => ($pet['tier_label'] ?? '') ?: null,
                    'archived_at' => WordPressSource::date($pet['archived_at'] ?? ''), 'archive_reason' => ($pet['archive_reason'] ?? '') ?: null,
                    'created_at' => WordPressSource::date($pet['updated_at'] ?? '') ?? now(), 'updated_at' => WordPressSource::date($pet['updated_at'] ?? '') ?? now(),
                ]);
                $counts['pets_imported']++;
            }
        }
    }

    /** Contractele istorice (CPT hv_abon_sub) se importa cu preturile si starea de atunci; PDF-urile originale nu se regenereaza. */
    private function contracts(bool $dryRun, array &$counts, array &$errors, array $userMap): void
    {
        $counts += ['contracts_in_source' => 0, 'contracts_imported' => 0, 'contracts_paid' => 0, 'contracts_without_pdf' => 0];
        if (! $this->source->has('posts')) {
            return;
        }

        foreach ($this->source->table('posts')->where('post_type', 'hv_abon_sub')->orderBy('ID')->cursor() as $post) {
            $counts['contracts_in_source']++;
            $meta = $this->source->table('postmeta')->where('post_id', $post->ID)->pluck('meta_value', 'meta_key');
            $payload = json_decode((string) ($meta['hv_payload'] ?? ''), true);
            if (! is_array($payload)) {
                $errors[] = ['wp_post_id' => (int) $post->ID, 'error' => 'Contract fără hv_payload valid; nu a fost importat.'];

                continue;
            }
            $paid = ($meta['hv_payment_status'] ?? '') === 'paid';
            $counts['contracts_paid'] += $paid ? 1 : 0;
            $counts['contracts_without_pdf'] += empty($meta['hv_pdf']) ? 1 : 0;
            $ownerId = $userMap[(int) ($meta['hv_client_user_id'] ?? 0)] ?? User::query()->where('email', mb_strtolower((string) ($payload['owner_email'] ?? '')))->value('id');
            if ($dryRun || ! $ownerId) {
                if (! $ownerId && ! $dryRun) {
                    $errors[] = ['wp_post_id' => (int) $post->ID, 'error' => 'Clientul contractului nu a fost găsit.'];
                }

                continue;
            }

            $months = in_array((int) ($meta['hv_payment_period_months'] ?? 1), [1, 6, 12], true) ? (int) $meta['hv_payment_period_months'] : 1;
            $paidAt = WordPressSource::date($meta['hv_payment_paid_at'] ?? '');
            $expires = $paidAt ? date('Y-m-d H:i:s', strtotime('+'.$months.' months', strtotime($paidAt))) : null;
            $status = ! $paid ? 'awaiting_clinic' : ($expires && strtotime($expires) > time() ? 'active' : 'expired');
            $petId = DB::table('pets')->where('user_id', $ownerId)->where('name', $payload['pet_name'] ?? '')->value('id');

            DB::table('contracts')->updateOrInsert(['wp_post_id' => (int) $post->ID], [
                'number' => 'WP-'.$post->ID, 'user_id' => $ownerId, 'pet_id' => $petId, 'submission_token' => (string) ($meta['hv_submission_token'] ?? 'wp-'.$post->ID),
                'status' => $status, 'species' => (string) ($payload['species'] ?? ($meta['hv_species'] ?? '')), 'tier_label' => (string) ($payload['tier_label'] ?? ($meta['hv_tier'] ?? '')),
                'pet_name' => (string) ($payload['pet_name'] ?? ''),
                'standard_monthly' => (int) ($payload['standard_price_monthly'] ?? $payload['price_monthly'] ?? 0), 'standard_6m' => (int) ($payload['standard_price_6m'] ?? $payload['price_6m'] ?? 0),
                'standard_12m' => (int) ($payload['standard_price_12m'] ?? $payload['price_12m'] ?? 0),
                'price_monthly' => (int) ($payload['price_monthly'] ?? 0), 'price_6m' => (int) ($payload['price_6m'] ?? 0), 'price_12m' => (int) ($payload['price_12m'] ?? 0),
                'discount_percent' => (int) ($payload['discount_percent'] ?? 0), 'discount_label' => $payload['discount_label'] ?? null, 'pet_ordinal' => (int) ($payload['pet_ordinal'] ?? 1),
                'total_normal' => (int) ($meta['hv_total_normal'] ?? 0), 'total_subscription' => (int) ($meta['hv_total_abon'] ?? 0),
                'period_months' => $months, 'payment_method' => 'clinic', 'payment_amount' => (float) ($meta['hv_payment_amount'] ?? 0),
                'payload' => encrypt(json_encode(array_diff_key($payload, ['signature' => 1]) + ['source_pdf' => $meta['hv_pdf'] ?? null], JSON_UNESCAPED_UNICODE), false),
                'legal_consents' => json_encode(['accepted' => $payload['legal'] ?? [], 'source' => 'import WordPress']),
                'signed_at' => $post->post_date, 'paid_at' => $paidAt, 'starts_at' => $paidAt, 'expires_at' => $expires,
                'created_at' => $post->post_date, 'updated_at' => $post->post_modified ?? $post->post_date,
            ]);
            $counts['contracts_imported']++;
        }
    }

    /** Registrul hv_wallet_tx se copiaza rand cu rand, cu ID-ul si data originale; soldurile rezulta din suma lui. */
    private function wallet(bool $dryRun, array &$counts, array &$errors, array $userMap): void
    {
        $counts += ['wallet_tx_in_source' => 0, 'wallet_tx_imported' => 0, 'wallet_tx_orphan' => 0, 'wallet_negative_balances' => 0];
        if (! $this->source->has('hv_wallet_tx')) {
            return;
        }

        $known = array_map(fn ($b) => $b->value, BalanceType::cases());
        foreach ($this->source->table('hv_wallet_tx')->orderBy('id')->cursor() as $row) {
            $counts['wallet_tx_in_source']++;
            $ownerId = $userMap[(int) $row->user_id] ?? null;
            if (! $ownerId || ! in_array($row->balance_type, $known, true)) {
                $counts['wallet_tx_orphan']++;
                $errors[] = ['wp_tx_id' => (int) $row->id, 'error' => ! $ownerId ? 'Tranzacție fără client importat.' : 'Tip de sold necunoscut: '.$row->balance_type];

                continue;
            }
            if ($dryRun) {
                continue;
            }
            DB::table('wallet_transactions')->updateOrInsert(['wp_tx_id' => (int) $row->id], [
                'user_id' => $ownerId, 'type' => (string) $row->type, 'balance_type' => (string) $row->balance_type, 'amount_delta' => (float) $row->amount_delta,
                'points_delta' => (int) $row->points_delta, 'note' => strip_tags((string) $row->note) ?: null, 'related_type' => $row->related_type ?: null,
                'related_id' => ((int) $row->related_id) ?: null, 'actor_name' => $row->actor_user_id ? 'WordPress #'.$row->actor_user_id : null,
                'created_at' => WordPressSource::date($row->created_at) ?? now(),
            ]);
            $counts['wallet_tx_imported']++;
        }

        // Reconciliere: soldurile din sursa, pe client si tip de sold; cele negative se raporteaza, nu se corecteaza.
        $counts['wallet_negative_balances'] = $this->source->table('hv_wallet_tx')->selectRaw('user_id, balance_type, SUM(amount_delta) AS a, SUM(points_delta) AS p')
            ->groupBy('user_id', 'balance_type')->get()->filter(fn ($r) => $r->balance_type !== 'wallet' && ((float) $r->a < -0.001 || (int) $r->p < 0))->count();
    }

    private function treatments(bool $dryRun, array &$counts, array $userMap): void
    {
        $counts += ['treatments_in_source' => 0, 'treatments_imported' => 0];
        if (! $this->source->has('hv_treatments')) {
            return;
        }

        foreach ($this->source->table('hv_treatments')->orderBy('id')->cursor() as $row) {
            $counts['treatments_in_source']++;
            $ownerId = $userMap[(int) $row->user_id] ?? null;
            if ($dryRun || ! $ownerId) {
                continue;
            }
            DB::table('treatments')->updateOrInsert(['wp_treatment_id' => (int) $row->id], [
                'user_id' => $ownerId, 'pet_name' => $row->pet_name ?: null, 'name' => (string) $row->treatment_name, 'price_standard' => (float) $row->price_standard,
                'points_used' => (int) $row->points_used, 'payment_category' => (string) ($row->payment_category ?? 'subscription'),
                'principal_used' => (float) ($row->principal_used ?? 0), 'bonus_used' => (float) ($row->bonus_used ?? 0), 'bonus_balance_type' => ($row->bonus_balance_type ?? '') ?: null,
                'bonus_blocked' => (bool) ($row->bonus_blocked ?? false), 'notes' => $row->notes ?: null, 'deleted_at' => WordPressSource::date($row->deleted_at ?? ''),
                'delete_reason' => ($row->delete_reason ?? '') ?: null, 'restored_at' => WordPressSource::date($row->restored_at ?? ''),
                'created_at' => WordPressSource::date($row->created_at) ?? now(), 'updated_at' => WordPressSource::date($row->created_at) ?? now(),
            ]);
            $counts['treatments_imported']++;
        }
    }

    /** Consimtamintele (CPT hv_consent) cu payload, semnaturi si momentul semnarii, exact ca in sursa. */
    private function consents(bool $dryRun, array &$counts, array $userMap): void
    {
        $counts += ['consents_in_source' => 0, 'consents_imported' => 0, 'consents_signed' => 0, 'consents_signed_without_signature' => 0];
        if (! $this->source->has('posts')) {
            return;
        }
        $service = app(ConsentService::class);

        foreach ($this->source->table('posts')->where('post_type', 'hv_consent')->orderBy('ID')->cursor() as $post) {
            $counts['consents_in_source']++;
            $meta = $this->source->table('postmeta')->where('post_id', $post->ID)->pluck('meta_value', 'meta_key');
            $payload = WordPressSource::meta($meta['hv_consent_payload'] ?? '');
            $payload = is_array($payload) ? $payload : [];
            $status = in_array($meta['hv_consent_status'] ?? '', ['pending', 'signed', 'cancelled'], true) ? $meta['hv_consent_status'] : 'pending';
            $ownerSignature = (string) ($meta['hv_consent_owner_signature'] ?? '');
            if ($status === 'signed') {
                $counts['consents_signed']++;
                $counts['consents_signed_without_signature'] += $ownerSignature === '' ? 1 : 0;
            }
            if ($dryRun) {
                continue;
            }

            $visit = array_values(array_filter((array) ($payload['visit_patients'] ?? []), 'is_array'));
            $enc = fn (string $v) => $v === '' ? null : encrypt($v, false);
            DB::table('consents')->updateOrInsert(['wp_post_id' => (int) $post->ID], [
                'token' => 'wp-'.$post->ID.'-'.Str::random(24), 'status' => $status,
                'origin' => empty($meta['hv_st_case_id']) ? 'import' : 'sterilization', 'client_id' => $userMap[(int) ($payload['client_user_id'] ?? 0)] ?? null,
                'owner_name' => ($payload['owner_name'] ?? '') ?: null,
                'pet_name' => $visit ? implode(', ', array_column($visit, 'pet_name')) : (($payload['pet_name'] ?? '') ?: null),
                'procedure' => ($payload['procedure'] ?? '') ?: null, 'vet_name' => ($payload['vet_name'] ?? '') ?: null,
                'consent_date' => WordPressSource::date($payload['consent_date'] ?? ''),
                // Payload-ul original se pastreaza integral, fara normalizare, pentru ca este proba semnata.
                'payload' => encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE), false),
                'owner_signature' => $enc($ownerSignature), 'refusal_signature' => $enc((string) ($meta['hv_consent_refusal_signature'] ?? '')),
                'doctor_signature' => $enc((string) ($meta['hv_consent_doctor_signature'] ?? '')),
                'doctor_signed_at' => WordPressSource::date($meta['hv_consent_doctor_signature_at'] ?? ''), 'signed_at' => WordPressSource::date($meta['hv_consent_signed_at'] ?? ''),
                'template_version' => 'wp-import', 'sterilization_case_id' => ((int) ($meta['hv_st_case_id'] ?? 0)) ?: null,
                'created_at' => $post->post_date, 'updated_at' => $post->post_modified ?? $post->post_date,
            ]);
            $counts['consents_imported']++;
        }
    }
}
