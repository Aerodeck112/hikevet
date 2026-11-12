<?php

namespace App\Services\Subscriptions;

use App\Enums\ContractStatus;
use App\Events\ContractSubmitted;
use App\Models\Contract;
use App\Models\ContractStatusHistory;
use App\Models\ContractTemplate;
use App\Models\PriceList;
use App\Models\User;
use App\Services\Clients\ClientAccounts;
use App\Services\Notifications\Notifier;
use App\Support\Audit;
use App\Support\Settings;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inregistrarea unui contract de abonament: portare a ajax_send() din simulator (SIM:910-1100)
 * si a process_submission_post() din Wallet Core (WAL:1587-1663), intr-o singura tranzactie.
 */
class ContractService
{
    public const LEGAL_KEYS = ['contract', 'annex', 'terms', 'guide', 'privacy', 'not_insurance'];

    public const SIGNATURE_PREFIX = 'data:image/png;base64,';

    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly ClientAccounts $accounts,
        private readonly ContractPdf $pdf,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array  $data  date deja validate ca format (vezi SubmitContractRequest)
     * @return array{contract: Contract, duplicate: bool, account_created: bool}
     */
    public function submit(array $data, ?User $authUser, ?string $ip = null, ?string $userAgent = null): array
    {
        $token = (string) ($data['submission_token'] ?? '');
        if ($token !== '' && ($existing = Contract::query()->where('submission_token', $token)->first())) {
            return ['contract' => $existing, 'duplicate' => true, 'account_created' => false];
        }

        $email = mb_strtolower(trim($data['owner_email']));
        $owner = $authUser;

        if ($owner === null && User::query()->where('email', $email)->exists()) {
            // Abatere deliberata fata de sursa: vechiul formular aplica reducerea si atasa contractul
            // la contul gasit dupa email, fara autentificare (vezi PLUGIN_AUDIT.md, 1.2 punctul 2).
            throw ValidationException::withMessages([
                'owner_email' => 'Există deja un cont cu acest email. Autentifică-te pentru a adăuga un animal în contul tău.',
            ]);
        }

        $list = PriceList::active()->load('tiers.services');
        $existingTiers = $this->pricing->existingTierLabels($owner);
        $offer = $this->pricing->offer($data['species'], (float) $data['weight'], $existingTiers, $list);

        if ($offer === null) {
            throw ValidationException::withMessages(['weight' => $data['species'] === 'dog'
                ? 'Greutatea câinelui trebuie să fie între 1,25 și 60 kg.'
                : 'Greutatea pisicii trebuie să fie între 0,5 și 70 kg.']);
        }

        $signatureBinary = $this->decodeSignature((string) $data['signature']);
        $period = in_array((int) ($data['payment_period_months'] ?? 1), PricingEngine::PERIODS, true) ? (int) $data['payment_period_months'] : 1;
        $breed = ($data['breed'] ?? '') === '__other__' ? trim((string) ($data['breed_other'] ?? '')) : trim((string) $data['breed']);
        $template = ContractTemplate::active();

        return DB::transaction(function () use ($data, $owner, $email, $offer, $list, $template, $signatureBinary, $period, $breed, $token, $ip, $userAgent) {
            $created = false;
            if ($owner === null) {
                [$owner, $created] = $this->accounts->findOrCreate($data['owner_name'], $email, $data['owner_phone'], $data['owner_addr']);
            } else {
                // La un contract nou se actualizeaza telefonul si adresa din cont (WAL:1611-1616).
                $owner->forceFill(['phone' => $data['owner_phone'], 'address' => $data['owner_addr']])->save();
            }

            $pet = $this->accounts->upsertPet($owner, [
                'name' => $data['pet_name'], 'species' => $data['species'], 'sex' => $data['sex'],
                'date_of_birth' => $data['dob'], 'breed' => $breed, 'weight_kg' => (float) $data['weight'],
                'microchip' => $data['pet_microchip'] ?? '', 'sterilized' => $data['sterilized'], 'tier_label' => $offer->tier->label,
            ]);

            $number = $this->nextNumber();
            $now = now();
            $stamp = $now->format('Ymd_His').'_'.Str::lower(Str::random(6));
            $dir = 'contracts/'.$now->format('Y/m');
            $signaturePath = $dir.'/semnatura_'.$stamp.'.png';
            Storage::disk('private')->put($signaturePath, $signatureBinary);

            $payload = [
                'pet_name' => $data['pet_name'], 'species' => $data['species'], 'sex' => $data['sex'], 'dob' => $data['dob'],
                'breed' => $breed, 'weight' => (float) $data['weight'], 'sterilized' => $data['sterilized'],
                'chronic' => $data['chronic'], 'chronic_text' => $data['chronic_text'] ?? '', 'emergency' => $data['emergency'],
                'routine' => array_values($data['routine'] ?? []),
                'owner_name' => $data['owner_name'], 'owner_email' => $email, 'owner_phone' => $data['owner_phone'],
                'owner_addr' => $data['owner_addr'], 'owner_cnp' => $data['owner_cnp'],
                'owner_ci_serie' => $data['owner_ci_serie'], 'owner_ci_nr' => $data['owner_ci_nr'],
                'pet_microchip' => $data['pet_microchip'] ?? '',
                'services' => array_map(fn ($s) => ['name' => $s['name'], 'normal' => $s['normal'], 'abon' => $s['abon']], $offer->services),
                'contract_number' => $number, 'contract_date' => $now->format('Y-m-d'),
            ];

            $contract = Contract::create([
                'number' => $number, 'user_id' => $owner->id, 'pet_id' => $pet->id, 'price_list_id' => $list->id,
                'contract_template_id' => $template->id, 'submission_token' => $token !== '' ? $token : (string) Str::uuid(),
                'status' => ContractStatus::AwaitingClinic, 'species' => $data['species'], 'tier_label' => $offer->tier->label,
                'pet_name' => $data['pet_name'],
                'standard_monthly' => $offer->standardMonthly, 'standard_6m' => $offer->standard6m, 'standard_12m' => $offer->standard12m,
                'price_monthly' => $offer->finalMonthly, 'price_6m' => $offer->final6m, 'price_12m' => $offer->final12m,
                'discount_percent' => $offer->discountPercent, 'discount_label' => $offer->discountLabel, 'pet_ordinal' => $offer->petOrdinal,
                'total_normal' => $offer->totalNormal, 'total_subscription' => $offer->totalSubscription,
                'period_months' => $period, 'payment_method' => 'clinic', 'payment_amount' => $offer->priceForPeriod($period),
                'payload' => $payload,
                'legal_consents' => [
                    'accepted' => array_fill_keys(self::LEGAL_KEYS, $now->toIso8601String()),
                    'documents' => $this->documentVersions(),
                ],
                'signature_path' => $signaturePath, 'signed_at' => $now, 'signed_ip' => $ip,
                'signed_user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
                'valid_until' => $now->copy()->addDays($list->contract_valid_days),
            ]);

            $pdfBytes = $this->pdf->render($contract, Storage::disk('private')->path($signaturePath));
            $pdfPath = $dir.'/contract_'.$stamp.'.pdf';
            Storage::disk('private')->put($pdfPath, $pdfBytes);
            $contract->forceFill(['pdf_path' => $pdfPath, 'pdf_sha256' => hash('sha256', $pdfBytes)])->save();

            ContractStatusHistory::create(['contract_id' => $contract->id, 'to_status' => ContractStatus::AwaitingClinic->value, 'actor_id' => $owner->id, 'note' => 'Contract semnat online']);
            Audit::log('contract.submitted', $contract, "Contract {$number} semnat online pentru {$pet->name}", null,
                ['tier' => $offer->tier->label, 'discount_percent' => $offer->discountPercent, 'period_months' => $period], $owner->id);

            DB::afterCommit(function () use ($contract, $owner, $pet, $created, $offer) {
                $this->sendEmails($contract, $owner, $pet->name, $created, $offer);
                ContractSubmitted::dispatch($contract, $created);
            });

            return ['contract' => $contract, 'duplicate' => false, 'account_created' => $created];
        });
    }

    public function transition(Contract $contract, ContractStatus $to, ?User $actor = null, string $note = ''): void
    {
        $from = $contract->status;
        if (! $from->canTransitionTo($to)) {
            throw new DomainException("Tranziție nepermisă: {$from->label()} → {$to->label()}.");
        }

        $contract->status = $to;
        if ($to === ContractStatus::Cancelled) {
            $contract->cancelled_at = now();
            $contract->cancel_reason = $note ?: null;
        }
        $contract->save();

        ContractStatusHistory::create(['contract_id' => $contract->id, 'from_status' => $from->value, 'to_status' => $to->value, 'actor_id' => $actor?->id, 'note' => $note ?: null]);
        Audit::log('contract.status', $contract, "Contract {$contract->number}: {$from->label()} → {$to->label()}", ['status' => $from->value], ['status' => $to->value], $contract->user_id);
    }

    private function decodeSignature(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, self::SIGNATURE_PREFIX)) {
            throw ValidationException::withMessages(['signature' => 'Semnătura lipsește. Semnează în chenar și încearcă din nou.']);
        }

        $binary = base64_decode(substr($dataUrl, strlen(self::SIGNATURE_PREFIX)), true);
        $info = $binary ? @getimagesizefromstring($binary) : false;

        if (! $binary || ! $info || $info[2] !== IMAGETYPE_PNG || strlen($binary) > 1_000_000 || $info[0] > 2000 || $info[1] > 1000) {
            throw ValidationException::withMessages(['signature' => 'Semnătura nu a putut fi procesată. Șterge semnătura și semnează din nou.']);
        }

        return $binary;
    }

    private function nextNumber(): string
    {
        $year = now()->format('Y');
        $last = Contract::query()->where('number', 'like', "HV-AB-{$year}-%")->lockForUpdate()->orderByDesc('id')->value('number');
        $next = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('HV-AB-%s-%05d', $year, $next);
    }

    /** Versiunea documentelor legale afisate la momentul semnarii, pentru auditul consimtamantului. */
    private function documentVersions(): array
    {
        return \App\Models\Page::query()
            ->whereIn('slug', ['contract-abonament', 'anexa-abonamente', 'termeni-si-conditii-abonamente', 'ghid-proprietar', 'politica-de-confidentialitate'])
            ->get(['slug', 'document_version', 'body', 'updated_at'])
            ->mapWithKeys(fn ($p) => [$p->slug => [
                'version' => $p->document_version ?? $p->updated_at?->format('Y-m-d'),
                'sha256' => hash('sha256', (string) $p->body),
            ]])->all();
    }

    private function sendEmails(Contract $contract, User $owner, string $petName, bool $created, Offer $offer): void
    {
        $attachment = [['path' => Storage::disk('private')->path($contract->pdf_path), 'name' => 'contract-'.$contract->number.'.pdf', 'mime' => 'application/pdf']];
        $subject = 'HikeVet - Contract abonament PDF semnat - '.$owner->name.' - '.$petName;

        $this->notifier->send(Settings::emails('mail.clinic_recipients'), $subject,
            '<p><strong>Client:</strong> '.e($owner->name).' ('.e($owner->email).')</p>'
            .'<p><strong>Animal:</strong> '.e($petName).' | <strong>Categorie:</strong> '.e($offer->tier->label).'</p>'
            .'<p><strong>Regula multi-animal:</strong> '.e($offer->discountLabel).'</p>'
            .'<p>Contractul PDF semnat este atașat. Plata se face fizic la clinică.</p>',
            'contract_clinic', 'contract', $contract->id, $owner->id, $attachment);

        $this->notifier->client($owner, 'Contract și ofertă HiKeVet înregistrate',
            'Cererea ta a fost înregistrată.<br>'
            .'Abonamentul devine activ după ce plata este confirmată la clinică; atunci se adaugă automat punctele în cont.<br>'
            .'Animal adăugat în cont: <strong>'.e($petName).'</strong>.<br>'
            .($created ? 'Ai primit separat emailul pentru activarea contului.<br>' : 'Contul tău a fost actualizat cu noul animal și noul contract.<br>')
            .'Contractul PDF este atașat și îl găsești oricând în contul tău.',
            'contract', $contract->id, true, $attachment);
    }
}
