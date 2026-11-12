<?php

namespace App\Services\Subscriptions;

use App\Enums\BalanceType;
use App\Enums\ContractStatus;
use App\Events\SubscriptionActivated;
use App\Models\Contract;
use App\Models\ContractStatusHistory;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Wallet\WalletLedger;
use App\Support\Audit;
use App\Support\Settings;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Confirmarea platii la clinica si activarea abonamentului: portare a
 * handle_validate_subscription() din Wallet Core (WAL:4275-4345).
 */
class SubscriptionActivation
{
    /** Cate zile inainte de expirare se poate inregistra reinnoirea. */
    public const RENEWAL_WINDOW_DAYS = 7;

    public function __construct(private readonly WalletLedger $ledger, private readonly Notifier $notifier) {}

    /** Suma de plata: pretul contractual al perioadei (cu reducerea de 6 / 12 luni). */
    public function amountFor(Contract $contract, int $months): int
    {
        return $contract->priceForPeriod($months);
    }

    /** Punctele nu folosesc reducerea de perioada: pret lunar contractual x luni (WAL:4258). */
    public function pointsFor(Contract $contract, int $months): int
    {
        return (int) round($contract->price_monthly * $months);
    }

    public function validatePayment(Contract $contract, int $months, User $actor): Contract
    {
        if (! in_array($months, PricingEngine::PERIODS, true)) {
            throw new DomainException('Perioada trebuie să fie 1, 6 sau 12 luni.');
        }

        return DB::transaction(function () use ($contract, $months, $actor) {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            if (! $contract->status->canTransitionTo(ContractStatus::Active)) {
                throw new DomainException('Contractul nu poate fi activat din starea „'.$contract->status->label().'”.');
            }

            $renewal = $contract->status === ContractStatus::Active;
            if ($renewal && $contract->expires_at && $contract->expires_at->gt(now()->addDays(self::RENEWAL_WINDOW_DAYS))) {
                throw new DomainException('Plata a fost deja validată. Abonamentul este activ până la '.$contract->expires_at->format('d.m.Y').'.');
            }

            $amount = $this->amountFor($contract, $months);
            $points = $this->pointsFor($contract, $months);
            $from = $contract->status;

            // Reinnoirea facuta inainte de expirare continua de la data expirarii; altfel porneste de acum.
            $start = ($renewal && $contract->expires_at && $contract->expires_at->isFuture()) ? $contract->expires_at->copy() : now();
            $expires = $start->copy()->addMonths($months);

            $this->ledger->record($contract->client, 'subscription_validated', BalanceType::Subscription, $amount, $points,
                'Validare abonament la clinică | contract '.$contract->number.' | sumă plătită: '.number_format($amount, 2, '.', '').' lei | puncte contract: '.$points,
                'subscription_validate', $contract->id, $actor, $contract->pet,
                'contract:'.$contract->id.':validate:'.$expires->format('Ymd'));

            $contract->forceFill([
                'status' => ContractStatus::Active, 'period_months' => $months, 'payment_amount' => $amount, 'payment_method' => 'clinic',
                'paid_at' => now(), 'validated_by' => $actor->id, 'starts_at' => $contract->starts_at ?? now(), 'expires_at' => $expires,
                'expiry_notice_sent_at' => null,
            ])->save();

            ContractStatusHistory::create(['contract_id' => $contract->id, 'from_status' => $from->value, 'to_status' => ContractStatus::Active->value,
                'actor_id' => $actor->id, 'note' => ($renewal ? 'Reînnoire' : 'Plată confirmată la clinică').' | '.$this->periodLabel($months)]);
            Audit::log('contract.payment_validated', $contract, "Plată confirmată pentru {$contract->number}: {$amount} lei, {$points} puncte", null,
                ['months' => $months, 'amount' => $amount, 'points' => $points, 'expires_at' => $expires->toDateString()], $contract->user_id);

            DB::afterCommit(function () use ($contract, $amount, $points, $months, $expires) {
                $this->notifier->client($contract->client, 'Abonament validat',
                    'Abonamentul tău a fost validat în clinică.<br>'
                    .'Suma plătită: <strong>'.number_format($amount, 2, ',', '.').' lei</strong>.<br>'
                    .'Perioada validată: <strong>'.$this->periodLabel($months).'</strong>.<br>'
                    .'Data expirării: <strong>'.$expires->format('d.m.Y').'</strong>.<br>'
                    .'Au fost adăugate <strong>'.$points.' puncte de abonament</strong>, calculate conform contractului.',
                    'subscription_validate', $contract->id);
                SubscriptionActivated::dispatch($contract, $points, (float) $amount);
            });

            return $contract;
        });
    }

    /** Rulat zilnic din scheduler: marcheaza expirarile, anuleaza ofertele neplatite si trimite avertizarile. */
    public function runDailyMaintenance(): array
    {
        $noticeDays = (int) Settings::get('subscriptions.expiry_notice_days', 5);
        $stats = ['expired' => 0, 'offers_lapsed' => 0, 'notices' => 0];

        Contract::query()->where('status', ContractStatus::Active)->where('expires_at', '<', now())->each(function (Contract $c) use (&$stats) {
            $c->forceFill(['status' => ContractStatus::Expired])->save();
            ContractStatusHistory::create(['contract_id' => $c->id, 'from_status' => 'active', 'to_status' => 'expired', 'note' => 'Expirare automată']);
            $stats['expired']++;
        });

        Contract::query()->where('status', ContractStatus::AwaitingClinic)->whereNotNull('valid_until')->where('valid_until', '<', now())->each(function (Contract $c) use (&$stats) {
            $c->forceFill(['status' => ContractStatus::OfferLapsed])->save();
            ContractStatusHistory::create(['contract_id' => $c->id, 'from_status' => 'awaiting_clinic', 'to_status' => 'offer_lapsed', 'note' => 'Termenul de plată a trecut']);
            $stats['offers_lapsed']++;
        });

        Contract::query()->with('client')->where('status', ContractStatus::Active)->whereNull('expiry_notice_sent_at')
            ->whereBetween('expires_at', [now(), now()->addDays($noticeDays)->endOfDay()])
            ->each(function (Contract $c) use (&$stats) {
                $days = max(0, (int) ceil(now()->diffInDays($c->expires_at, false)));
                $this->notifier->client($c->client, 'Abonamentul HiKeVet se apropie de expirare',
                    'Bună ziua,<br><br>Abonamentul HiKeVet pentru <strong>'.e($c->pet_name).'</strong> expiră '
                    .($days > 0 ? 'în aproximativ <strong>'.$days.' zile</strong>, ' : '')
                    .'la data de <strong>'.$c->expires_at->format('d.m.Y').'</strong>.<br>Categorie abonament: <strong>'.e($c->tier_label).'</strong>.<br><br>'
                    .'Te rugăm să contactezi clinica pentru plata sau reînnoirea abonamentului.', 'subscription_expiry', $c->id);
                $this->notifier->send(Settings::emails('mail.clinic_recipients'), 'HikeVet - abonament aproape de termenul de plată',
                    'Client: <strong>'.e($c->client->name).'</strong><br>Email: <strong>'.e($c->client->email).'</strong><br>Animal: <strong>'.e($c->pet_name).'</strong><br>'
                    .'Categorie: <strong>'.e($c->tier_label).'</strong><br>Data expirării: <strong>'.$c->expires_at->format('d.m.Y').'</strong><br>Zile rămase: <strong>'.$days.'</strong>',
                    'subscription_expiry_clinic', 'subscription_expiry', $c->id, $c->user_id);
                $c->forceFill(['expiry_notice_sent_at' => now()])->save();
                $stats['notices']++;
            });

        return $stats;
    }

    public function periodLabel(int $months): string
    {
        return match ($months) {
            12 => '12 luni', 6 => '6 luni', default => 'lunar',
        };
    }
}
