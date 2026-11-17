<?php

namespace App\Services\Import;

use App\Enums\BalanceType;
use App\Enums\Role;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Consents\ConsentService;
use App\Services\Legacy\LegacyArchive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import pentru conturi, animale, contracte, registrul Wallet, tratamente si consimtaminte.
 * Idempotent: fiecare entitate are cheia originala WordPress (wp_user_id, wp_post_id, wp_tx_id...).
 * Timestampurile originale se pastreaza; parolele raman ca hash WordPress pana la primul login.
 */
class WordPressCoreImporter
{
    public const KIND = 'wordpress_core';

    /** @var array<int,string>|null wp user id => nume afisat, pentru toti utilizatorii WordPress (inclusiv personalul) */
    private ?array $wpNames = null;

    private ?string $uploadsDir = null;

    public function __construct(private readonly WordPressSource $source) {}

    /** @param string|null $uploadsDir copia wp-content/uploads, pentru PDF-urile si semnaturile contractelor vechi */
    public function run(bool $dryRun = true, ?int $userId = null, ?string $uploadsDir = null): ImportBatch
    {
        $this->uploadsDir = $uploadsDir;
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
            $this->notifications($dryRun, $counts, $userMap);
            $this->emailLog($dryRun, $counts, $userMap);
            $this->auditLog($dryRun, $counts, $userMap);
            $this->doctorNotes($dryRun, $counts, $userMap);
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
                // In proba, clientii care ar fi creati primesc un ID provizoriu negativ, ca tranzactiile si notitele lor
                // sa nu fie numarate gresit drept „fara client”. In proba nu se scrie nimic, deci ID-ul nu ajunge nicaieri.
                $map[(int) $row->ID] = $existing?->id ?? -1;

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
    /**
     * Contractele (CPT hv_abon_sub). In WordPress starea platii nu sta sigur pe contract: Wallet Core scrie
     * hv_payment_* doar pe „ultimul contract” al clientului, iar istoricul complet al platilor este in registru
     * (tranzactiile subscription_validated / subscription_paid_card, cu related_id = contractul). Starea se
     * reconstruieste din aceste plati reale; starea scrisa de WordPress se pastreaza in payload, pentru comparatie.
     */
    private function contracts(bool $dryRun, array &$counts, array &$errors, array $userMap): void
    {
        $counts += ['contracts_in_source' => 0, 'contracts_imported' => 0, 'contracts_paid' => 0, 'contracts_active' => 0, 'contracts_expired' => 0,
            'contracts_unpaid' => 0, 'contract_payments' => 0, 'contracts_payload_repaired' => 0, 'contracts_without_pdf' => 0];
        if (! $this->source->has('posts')) {
            return;
        }

        $payments = [];
        if ($this->source->has('hv_wallet_tx')) {
            foreach ($this->source->table('hv_wallet_tx')->whereIn('type', ['subscription_validated', 'subscription_paid_card'])->where('related_id', '>', 0)->orderBy('created_at')->orderBy('id')->get() as $tx) {
                $payments[(int) $tx->related_id][] = $tx;
            }
        }
        // Ultima validare a fiecarui client, cu perioada si expirarea calculate de WordPress.
        $lastValidated = [];
        foreach ($this->source->table('usermeta')->whereIn('meta_key', ['hv_last_validated_submission_id', 'hv_last_validated_period_months', 'hv_subscription_expires_at', 'hv_last_validated_subscription_at'])->get() as $m) {
            $lastValidated[(int) $m->user_id][$m->meta_key] = $m->meta_value;
        }
        $bySubmission = [];
        foreach ($lastValidated as $meta) {
            if (! empty($meta['hv_last_validated_submission_id'])) {
                $bySubmission[(int) $meta['hv_last_validated_submission_id']] = $meta;
            }
        }
        $validDays = (int) (DB::table('price_lists')->where('is_active', true)->value('contract_valid_days') ?: 5);

        foreach ($this->source->table('posts')->where('post_type', 'hv_abon_sub')->orderBy('ID')->cursor() as $post) {
            $counts['contracts_in_source']++;
            $meta = $this->source->table('postmeta')->where('post_id', $post->ID)->pluck('meta_value', 'meta_key');
            [$payload, $repaired] = $this->decodePayload((string) ($meta['hv_payload'] ?? ''));
            if (! is_array($payload)) {
                $errors[] = ['wp_post_id' => (int) $post->ID, 'error' => 'Contract fără hv_payload citibil; nu a fost importat.'];

                continue;
            }
            $counts['contracts_payload_repaired'] += $repaired ? 1 : 0;
            $counts['contracts_without_pdf'] += empty($meta['hv_pdf']) ? 1 : 0;
            $pdfFile = empty($meta['hv_pdf']) ? null : $this->resolveUpload((string) $meta['hv_pdf']);
            if (! empty($meta['hv_pdf'])) {
                $counts['contract_pdfs_found'] = ($counts['contract_pdfs_found'] ?? 0) + ($pdfFile ? 1 : 0);
                if (! $pdfFile && $this->uploadsDir) {
                    $errors[] = ['wp_post_id' => (int) $post->ID, 'error' => 'PDF-ul contractului nu a fost găsit în copia uploads: '.basename((string) $meta['hv_pdf'])];
                }
            }

            // Platile acestui contract: din registru; daca registrul nu are niciuna, din meta contractului.
            $paid = $payments[(int) $post->ID] ?? [];
            if (! $paid && ($meta['hv_payment_status'] ?? '') === 'paid' && ! empty($meta['hv_payment_paid_at'])) {
                $paid = [(object) ['id' => null, 'type' => 'contract_meta', 'amount_delta' => (float) ($meta['hv_payment_amount'] ?? 0), 'created_at' => $meta['hv_payment_paid_at']]];
            }
            $counts['contract_payments'] += count($paid);
            $prices = ['price_monthly' => (int) ($payload['price_monthly'] ?? 0), 'price_6m' => (int) ($payload['price_6m'] ?? 0), 'price_12m' => (int) ($payload['price_12m'] ?? 0)];
            $periodFor = function (object $payment, bool $isLast) use ($meta, $prices, $bySubmission, $post): int {
                if ($isLast && isset($bySubmission[(int) $post->ID]['hv_last_validated_period_months'])) {
                    return (int) $bySubmission[(int) $post->ID]['hv_last_validated_period_months'];
                }
                $amount = (int) round((float) $payment->amount_delta);

                return match (true) {
                    $amount > 0 && $amount === $prices['price_12m'] => 12,
                    $amount > 0 && $amount === $prices['price_6m'] => 6,
                    $amount > 0 && $amount === $prices['price_monthly'] => 1,
                    in_array((int) ($meta['hv_payment_period_months'] ?? 0), [1, 6, 12], true) => (int) $meta['hv_payment_period_months'],
                    default => 1,
                };
            };

            $status = 'awaiting_clinic';
            $months = 1;
            $first = $last = $expires = null;
            $lastAmount = (float) ($meta['hv_payment_amount'] ?? 0);
            if ($paid) {
                $first = WordPressSource::date($paid[0]->created_at);
                $lastPayment = end($paid);
                $last = WordPressSource::date($lastPayment->created_at);
                $months = $periodFor($lastPayment, true);
                $lastAmount = (float) $lastPayment->amount_delta ?: $lastAmount;
                $expires = WordPressSource::date($bySubmission[(int) $post->ID]['hv_subscription_expires_at'] ?? '')
                    ?? date('Y-m-d H:i:s', strtotime('+'.$months.' months', strtotime($last)));
                $status = strtotime($expires) > time() ? 'active' : 'expired';
                $counts['contracts_paid']++;
                $counts[$status === 'active' ? 'contracts_active' : 'contracts_expired']++;
            } else {
                $counts['contracts_unpaid']++;
                // Neplatit: oferta ramane deschisa doar in termenul din contract, ca pentru contractele noi.
                $status = strtotime($post->post_date.' +'.$validDays.' days') > time() ? 'awaiting_clinic' : 'offer_lapsed';
            }

            $ownerId = $userMap[(int) ($meta['hv_client_user_id'] ?? 0)] ?? User::query()->where('email', mb_strtolower((string) ($payload['owner_email'] ?? '')))->value('id');
            if (! $ownerId) {
                // Fara cont si fara email valid nu se poate crea un client fara a inventa date. Contractul ramane intreg
                // in arhiva WordPress, iar PDF-ul lui se pastreaza criptat, ca fisier de arhiva.
                $counts['contracts_without_client'] = ($counts['contracts_without_client'] ?? 0) + 1;
                $errors[] = ['wp_post_id' => (int) $post->ID, 'error' => 'Contract fără client: niciun cont și email invalid în formular ('
                    .(filter_var((string) ($payload['owner_email'] ?? ''), FILTER_VALIDATE_EMAIL) ? 'email fără cont' : 'email invalid').'); păstrat în Arhiva WordPress.'];
                if (! $dryRun && $pdfFile) {
                    app(LegacyArchive::class)->storeFile($pdfFile, 'contract-fara-client', 'WP-'.$post->ID.' ('.substr((string) $post->post_date, 0, 10).')');
                }

                continue;
            }
            if ($dryRun) {
                continue;
            }

            $petId = DB::table('pets')->where('user_id', $ownerId)->where('name', $payload['pet_name'] ?? '')->value('id');
            $files = $pdfFile ? $this->copyContractFiles($pdfFile) : [];
            $stored = array_diff_key($payload, ['signature' => 1]) + ['source_pdf' => $meta['hv_pdf'] ?? null,
                'source_payment_status' => $meta['hv_payment_status'] ?? null, 'source_payment_meta' => array_filter([
                    'period_months' => $meta['hv_payment_period_months'] ?? null, 'amount' => $meta['hv_payment_amount'] ?? null, 'paid_at' => $meta['hv_payment_paid_at'] ?? null])];

            DB::table('contracts')->updateOrInsert(['wp_post_id' => (int) $post->ID], [
                'number' => 'WP-'.$post->ID, 'user_id' => $ownerId, 'pet_id' => $petId, 'submission_token' => (string) ($meta['hv_submission_token'] ?? 'wp-'.$post->ID),
                'status' => $status, 'species' => (string) ($payload['species'] ?? ($meta['hv_species'] ?? '')), 'tier_label' => (string) ($payload['tier_label'] ?? ($meta['hv_tier'] ?? '')),
                'pet_name' => (string) ($payload['pet_name'] ?? ''),
                'standard_monthly' => (int) ($payload['standard_price_monthly'] ?? $payload['price_monthly'] ?? 0), 'standard_6m' => (int) ($payload['standard_price_6m'] ?? $payload['price_6m'] ?? 0),
                'standard_12m' => (int) ($payload['standard_price_12m'] ?? $payload['price_12m'] ?? 0),
                'price_monthly' => $prices['price_monthly'], 'price_6m' => $prices['price_6m'], 'price_12m' => $prices['price_12m'],
                'discount_percent' => (int) ($payload['discount_percent'] ?? 0), 'discount_label' => $payload['discount_label'] ?? null, 'pet_ordinal' => (int) ($payload['pet_ordinal'] ?? 1),
                'total_normal' => (int) ($meta['hv_total_normal'] ?? 0), 'total_subscription' => (int) ($meta['hv_total_abon'] ?? 0),
                'period_months' => $months, 'payment_method' => 'clinic', 'payment_amount' => $lastAmount,
                'payload' => encrypt(json_encode($stored, JSON_UNESCAPED_UNICODE), false),
                'legal_consents' => json_encode(['accepted' => $payload['legal'] ?? array_intersect_key($payload, array_flip(['contract', 'annex', 'terms', 'guide', 'privacy', 'not_insurance'])), 'source' => 'import WordPress']),
                'signed_at' => $post->post_date, 'paid_at' => $last, 'starts_at' => $first, 'expires_at' => $expires,
                'valid_until' => date('Y-m-d H:i:s', strtotime($post->post_date.' +'.$validDays.' days')),
                'pdf_path' => $files['pdf'] ?? null, 'pdf_sha256' => $files['pdf_sha256'] ?? null, 'signature_path' => $files['signature'] ?? null,
                'legacy_pdf_reference' => ($meta['hv_pdf'] ?? '') ?: null,
                'created_at' => $post->post_date, 'updated_at' => $post->post_modified ?? $post->post_date,
            ]);
            $contractId = (int) DB::table('contracts')->where('wp_post_id', (int) $post->ID)->value('id');

            // Istoricul contractului, refacut la fiecare import: semnare, fiecare plata, starea finala.
            DB::table('contract_status_history')->where('contract_id', $contractId)->where('note', 'like', 'WordPress:%')->delete();
            $history = [['from_status' => null, 'to_status' => 'awaiting_clinic', 'note' => 'WordPress: contract semnat online', 'created_at' => $post->post_date]];
            foreach ($paid as $i => $payment) {
                $history[] = ['from_status' => $i === 0 ? 'awaiting_clinic' : 'active', 'to_status' => 'active', 'created_at' => WordPressSource::date($payment->created_at) ?? $post->post_date,
                    'note' => mb_substr('WordPress: '.($payment->type === 'subscription_paid_card' ? 'plată cu cardul' : ($i === 0 ? 'plată validată la clinică' : 'reînnoire validată la clinică'))
                        .(((float) $payment->amount_delta) > 0 ? ', '.rtrim(rtrim(number_format((float) $payment->amount_delta, 2, '.', ''), '0'), '.').' lei' : '')
                        .', '.(($n = $periodFor($payment, $i === count($paid) - 1)) === 1 ? '1 lună' : $n.' luni').($payment->id ? ' (tranzacția #'.$payment->id.')' : ''), 0, 255)];
            }
            if ($status === 'expired') {
                $history[] = ['from_status' => 'active', 'to_status' => 'expired', 'note' => 'WordPress: abonament expirat', 'created_at' => $expires];
            } elseif ($status === 'offer_lapsed') {
                $history[] = ['from_status' => 'awaiting_clinic', 'to_status' => 'offer_lapsed', 'note' => 'WordPress: neplătit în termenul ofertei', 'created_at' => date('Y-m-d H:i:s', strtotime($post->post_date.' +'.$validDays.' days'))];
            }
            DB::table('contract_status_history')->insert(array_map(fn ($h) => $h + ['contract_id' => $contractId, 'actor_id' => null], $history));
            $counts['contracts_imported']++;
        }
    }

    /**
     * hv_payload este JSON. WordPress scoate backslash-urile la salvarea in meta, asa ca un camp cu HTML
     * (contract_preview_html) poate ajunge cu ghilimele neescapate. Atunci se pastreaza toate campurile
     * dinaintea celui stricat, iar textul original complet ramane in payload ca dovada.
     *
     * @return array{0:?array,1:bool} payload, daca a fost reparat
     */
    private function decodePayload(string $raw): array
    {
        $payload = json_decode($raw, true);
        if (is_array($payload)) {
            return [$payload, false];
        }
        if ($raw === '' || ! str_starts_with($raw, '{')) {
            return [null, false];
        }
        preg_match_all('/,"([a-z0-9_]+)":/', $raw, $m, PREG_OFFSET_CAPTURE);
        foreach (array_reverse($m[0]) as [, $offset]) {
            $prefix = json_decode(substr($raw, 0, $offset).'}', true);
            if (is_array($prefix) && $prefix !== []) {
                $broken = [];
                preg_match_all('/,"([a-z0-9_]+)":/', substr($raw, $offset), $rest);

                return [$prefix + ['_import_note' => 'Câmpuri cu text deteriorat în WordPress (ghilimele neescapate): '.implode(', ', array_unique($rest[1])).'. Originalul complet este în _source_raw.',
                    '_source_raw' => $raw], true];
            }
        }

        return [null, false];
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
                'related_id' => ((int) $row->related_id) ?: null, 'actor_name' => $this->wpName((int) $row->actor_user_id),
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

    /** Notificarile din contul clientului (hv_notifications). */
    private function notifications(bool $dryRun, array &$counts, array $userMap): void
    {
        $counts += ['notifications_in_source' => 0, 'notifications_imported' => 0, 'notifications_orphan' => 0];
        if (! $this->source->has('hv_notifications')) {
            return;
        }
        foreach ($this->source->table('hv_notifications')->orderBy('id')->cursor() as $row) {
            $counts['notifications_in_source']++;
            $ownerId = $userMap[(int) $row->user_id] ?? null;
            if (! $ownerId) {
                $counts['notifications_orphan']++;

                continue;
            }
            if ($dryRun) {
                continue;
            }
            DB::table('client_notifications')->updateOrInsert(['wp_notification_id' => (int) $row->id], [
                'user_id' => $ownerId, 'subject' => mb_substr((string) $row->subject, 0, 255), 'message' => (string) $row->message,
                'channel' => ($row->channel ?? '') ?: 'email', 'related_type' => ($row->related_type ?? '') ?: null,
                'related_id' => ((int) ($row->related_id ?? 0)) ?: null,
                // Istoric: notificarile vechi nu apar ca necitite in contul nou.
                'read_at' => WordPressSource::date($row->created_at) ?? now(), 'created_at' => WordPressSource::date($row->created_at) ?? now(),
            ]);
            $counts['notifications_imported']++;
        }
    }

    /** Jurnalul emailurilor trimise de Wallet Core (hv_email_log). Pastreaza si emailurile fara client. */
    private function emailLog(bool $dryRun, array &$counts, array $userMap): void
    {
        $counts += ['email_log_in_source' => 0, 'email_log_imported' => 0, 'email_log_failed_in_source' => 0];
        if (! $this->source->has('hv_email_log')) {
            return;
        }
        foreach ($this->source->table('hv_email_log')->orderBy('id')->cursor() as $row) {
            $counts['email_log_in_source']++;
            $counts['email_log_failed_in_source'] += in_array($row->status, ['failed', 'error'], true) ? 1 : 0;
            if ($dryRun) {
                continue;
            }
            DB::table('email_logs')->updateOrInsert(['wp_email_log_id' => (int) $row->id], [
                'user_id' => $userMap[(int) $row->user_id] ?? null, 'to_email' => (string) $row->to_email, 'subject' => mb_substr((string) $row->subject, 0, 255),
                'context' => ($row->context ?? '') ?: null, 'status' => ($row->status ?? '') ?: 'sent', 'error_message' => ($row->error_message ?? '') ?: null,
                'related_type' => ($row->related_type ?? '') ?: null, 'related_id' => ((int) ($row->related_id ?? 0)) ?: null,
                'created_at' => WordPressSource::date($row->created_at) ?? now(),
            ]);
            $counts['email_log_imported']++;
        }
    }

    /** Jurnalul de audit al Wallet Core (hv_audit_log), cu valorile dinainte si de dupa exact ca in sursa. */
    private function auditLog(bool $dryRun, array &$counts, array $userMap): void
    {
        $counts += ['audit_in_source' => 0, 'audit_imported' => 0];
        if (! $this->source->has('hv_audit_log')) {
            return;
        }
        $asJson = function ($value) {
            if ($value === null || $value === '') {
                return null;
            }
            $decoded = json_decode((string) $value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }
            $meta = WordPressSource::meta((string) $value);

            return json_encode(is_array($meta) ? $meta : ['valoare' => (string) $value], JSON_UNESCAPED_UNICODE);
        };
        foreach ($this->source->table('hv_audit_log')->orderBy('id')->cursor() as $row) {
            $counts['audit_in_source']++;
            if ($dryRun) {
                continue;
            }
            // Insert direct in tabel: modelul AuditLog nu permite modificari, iar importul e repetabil.
            DB::table('audit_logs')->updateOrInsert(['wp_audit_id' => (int) $row->id], [
                'actor_name' => $this->wpName((int) ($row->actor_user_id ?? 0)) ?? 'sistem WordPress', 'action' => 'wp.'.mb_substr((string) $row->action, 0, 57),
                'subject_type' => ($row->entity_type ?? '') ? 'wp:'.$row->entity_type : null, 'subject_id' => ((int) ($row->entity_id ?? 0)) ?: null,
                'client_id' => $userMap[(int) ($row->user_id ?? 0)] ?? null, 'summary' => ($row->summary ?? '') ?: null,
                'before' => $asJson($row->before_value ?? null), 'after' => $asJson($row->after_value ?? null),
                'created_at' => WordPressSource::date($row->created_at) ?? now(),
            ]);
            $counts['audit_imported']++;
        }
    }

    /** Notitele medicilor (hv_doctor_notes), inclusiv cele sterse, cu autorul pastrat ca nume. */
    private function doctorNotes(bool $dryRun, array &$counts, array $userMap): void
    {
        $counts += ['doctor_notes_in_source' => 0, 'doctor_notes_imported' => 0, 'doctor_notes_orphan' => 0];
        if (! $this->source->has('hv_doctor_notes')) {
            return;
        }
        foreach ($this->source->table('hv_doctor_notes')->orderBy('id')->cursor() as $row) {
            $counts['doctor_notes_in_source']++;
            $ownerId = $userMap[(int) $row->user_id] ?? null;
            if (! $ownerId) {
                $counts['doctor_notes_orphan']++;

                continue;
            }
            if ($dryRun) {
                continue;
            }
            $petId = ((int) ($row->pet_id ?? 0)) ? DB::table('pets')->where('user_id', $ownerId)->where('wp_pet_id', (int) $row->pet_id)->value('id') : null;
            DB::table('doctor_notes')->updateOrInsert(['wp_note_id' => (int) $row->id], [
                'user_id' => $ownerId, 'pet_id' => $petId, 'note_text' => (string) $row->note_text,
                'priority' => ($row->priority ?? '') ?: 'normal', 'status' => ($row->note_status ?? '') ?: 'open',
                'actor_name' => $this->wpName((int) ($row->actor_user_id ?? 0)),
                'deleted_at' => WordPressSource::date($row->deleted_at ?? ''), 'deleted_by_name' => $this->wpName((int) ($row->deleted_by ?? 0)),
                'created_at' => WordPressSource::date($row->created_at) ?? now(), 'updated_at' => WordPressSource::date($row->updated_at ?? '') ?? WordPressSource::date($row->created_at) ?? now(),
            ]);
            $counts['doctor_notes_imported']++;
        }
    }

    /** Numele afisat al unui utilizator WordPress (client sau personal), pentru istoric. */
    private function wpName(int $wpUserId): ?string
    {
        if ($wpUserId <= 0) {
            return null;
        }
        if ($this->wpNames === null) {
            $this->wpNames = $this->source->has('users') ? $this->source->table('users')->pluck('display_name', 'ID')->map(fn ($n) => trim((string) $n))->all() : [];
        }
        $name = $this->wpNames[$wpUserId] ?? '';

        return $name !== '' ? $name.' (WordPress #'.$wpUserId.')' : 'WordPress #'.$wpUserId;
    }

    /** Gaseste un fisier din wp-content/uploads dupa calea sau URL-ul salvat de WordPress, fara a iesi din director. */
    private function resolveUpload(string $reference): ?string
    {
        if (! $this->uploadsDir || ! is_dir($this->uploadsDir)) {
            return null;
        }
        $path = parse_url($reference, PHP_URL_PATH) ?: $reference;
        $relative = str_contains($path, '/uploads/') ? substr($path, strrpos($path, '/uploads/') + 9) : basename($path);
        $root = realpath($this->uploadsDir);
        $candidate = realpath($root.'/'.ltrim($relative, '/'));

        return ($candidate && $root && str_starts_with($candidate, $root.DIRECTORY_SEPARATOR) && is_file($candidate)) ? $candidate : null;
    }

    /**
     * Copiaza PDF-ul contractului vechi si semnatura lui (semnatura_<marcaj>.jpg, in acelasi director) pe discul privat.
     * Copiile sunt bit cu bit; amprenta se verifica dupa copiere.
     *
     * @return array{pdf?:string,pdf_sha256?:string,signature?:string}
     */
    private function copyContractFiles(string $pdfFile): array
    {
        $result = [];
        $copy = function (string $file, string $kind): array {
            $hash = hash_file('sha256', $file);
            $stored = 'contracts/legacy/'.$kind.'-'.$hash.'.'.strtolower(pathinfo($file, PATHINFO_EXTENSION) ?: 'bin');
            if (! Storage::disk('private')->exists($stored)) {
                Storage::disk('private')->put($stored, file_get_contents($file));
            }
            if (hash('sha256', Storage::disk('private')->get($stored)) !== $hash) {
                throw new \RuntimeException('Amprenta fișierului copiat diferă de original: '.basename($file));
            }

            return [$stored, $hash];
        };
        [$result['pdf'], $result['pdf_sha256']] = $copy($pdfFile, 'contract');
        if (preg_match('/^contract_(.+)\.pdf$/i', basename($pdfFile), $m)) {
            $signature = dirname($pdfFile).'/semnatura_'.$m[1].'.jpg';
            if (is_file($signature)) {
                [$result['signature']] = $copy($signature, 'semnatura');
            }
        }

        return $result;
    }
}
