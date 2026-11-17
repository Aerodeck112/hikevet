<?php

namespace App\Services\Payments;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\OnlinePayment;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Subscriptions\PricingEngine;
use App\Services\Subscriptions\SubscriptionActivation;
use App\Services\Wallet\WalletLedger;
use App\Support\Audit;
use App\Support\Settings;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Plata online cu cardul prin Stripe Checkout: clientul plateste pe pagina Stripe, deci datele cardului nu ajung
 * niciodata pe server. Plata se confirma din trei directii, toate idempotente: la intoarcerea clientului, prin
 * webhook si printr-o verificare orara a platilor ramase in curs. Alimentarea Wallet-ului si activarea abonamentului
 * folosesc exact aceleasi reguli ca la clinica (acelasi bonus, aceleasi puncte, aceleasi preturi contractuale).
 */
class StripeCheckout
{
    public const API = 'https://api.stripe.com/v1';

    public const WALLET_MIN = 10;

    public const WALLET_MAX = 20000;

    public function __construct(private readonly WalletLedger $ledger, private readonly SubscriptionActivation $activation, private readonly Notifier $notifier) {}

    public static function secret(): string
    {
        return (string) (config('services.stripe.secret') ?: Settings::get('payments.stripe_secret', ''));
    }

    public static function webhookSecret(): string
    {
        return (string) (config('services.stripe.webhook_secret') ?: Settings::get('payments.stripe_webhook_secret', ''));
    }

    public static function configured(): bool
    {
        return (bool) preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', self::secret());
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('payments.stripe_enabled', false) && self::configured();
    }

    public static function liveMode(): bool
    {
        return (bool) preg_match('/^(sk|rk)_live_/', self::secret());
    }

    /** Abonamentul se poate plati online daca asteapta plata, a expirat sau este in ultimele zile inainte de expirare. */
    public static function canPayOnline(Contract $contract): bool
    {
        return match ($contract->status) {
            ContractStatus::AwaitingClinic => ! $contract->valid_until || $contract->valid_until->isFuture(),
            ContractStatus::Expired => true,
            ContractStatus::Active => $contract->expires_at && $contract->expires_at->lte(now()->addDays(SubscriptionActivation::RENEWAL_WINDOW_DAYS)),
            default => false,
        };
    }

    private static function ensureEnabled(): void
    {
        if (! self::enabled()) {
            throw new DomainException('Plata online nu este disponibilă momentan. Te rugăm să plătești la clinică.');
        }
    }

    public function startWalletTopup(User $user, float $amount): array
    {
        self::ensureEnabled();
        $amount = round($amount, 2);
        if ($amount < self::WALLET_MIN || $amount > self::WALLET_MAX) {
            throw new DomainException('Suma trebuie să fie între '.self::WALLET_MIN.' și '.self::WALLET_MAX.' lei.');
        }
        if (! $this->ledger->hasActiveSubscription($user)) {
            throw new DomainException('Wallet-ul poate fi alimentat doar cu abonament activ.');
        }

        return $this->start(OnlinePayment::query()->create(['user_id' => $user->id, 'purpose' => OnlinePayment::WALLET, 'amount' => $amount, 'live_mode' => self::liveMode()]));
    }

    public function startSubscription(User $user, Contract $contract, int $months): array
    {
        self::ensureEnabled();
        if ($contract->user_id !== $user->id) {
            throw new DomainException('Contractul nu aparține contului tău.');
        }
        if (! in_array($months, PricingEngine::PERIODS, true)) {
            throw new DomainException('Perioada trebuie să fie 1, 6 sau 12 luni.');
        }
        if (! self::canPayOnline($contract)) {
            throw new DomainException('Acest abonament nu poate fi plătit online acum. Pentru detalii, contactează clinica.');
        }

        return $this->start(OnlinePayment::query()->create(['user_id' => $user->id, 'purpose' => OnlinePayment::SUBSCRIPTION, 'contract_id' => $contract->id,
            'months' => $months, 'amount' => $this->activation->amountFor($contract, $months), 'live_mode' => self::liveMode()]));
    }

    /** @return array{payment:OnlinePayment,url:string} */
    private function start(OnlinePayment $payment): array
    {
        $payment->loadMissing('user', 'contract');
        $base = url('/panou-cont/plata/'.$payment->id);
        $response = Http::asForm()->withToken(self::secret())->withHeaders(['Idempotency-Key' => 'hikevet-payment-'.$payment->id])->timeout(20)
            ->post(self::API.'/checkout/sessions', [
                'mode' => 'payment', 'locale' => 'ro', 'customer_email' => $payment->user->email, 'client_reference_id' => (string) $payment->id,
                'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'ron', 'unit_amount' => (int) round($payment->amount * 100),
                    'product_data' => ['name' => $payment->description()]]]],
                'metadata' => ['payment_id' => (string) $payment->id, 'purpose' => $payment->purpose],
                'payment_intent_data' => ['description' => $payment->description(), 'metadata' => ['payment_id' => (string) $payment->id]],
                // {CHECKOUT_SESSION_ID} este completat de Stripe; nu trebuie codat.
                'success_url' => $base.'/confirmare?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $base.'/renuntare',
                'expires_at' => now()->addHour()->timestamp,
            ]);
        if (! $response->successful() || ! $response->json('url')) {
            $payment->update(['status' => 'failed', 'review_reason' => mb_substr('Stripe: '.($response->json('error.message') ?: 'răspuns '.$response->status()), 0, 500)]);
            report(new RuntimeException('Stripe checkout: '.$response->status().' '.$response->json('error.message')));

            throw new DomainException('Plata online nu a putut fi pornită. Încearcă din nou peste câteva minute sau plătește la clinică.');
        }
        $payment->update(['stripe_session_id' => $response->json('id')]);

        return ['payment' => $payment, 'url' => (string) $response->json('url')];
    }

    public function fetchSession(string $sessionId): ?array
    {
        $response = Http::withToken(self::secret())->timeout(20)->get(self::API.'/checkout/sessions/'.rawurlencode($sessionId));

        return $response->successful() ? $response->json() : null;
    }

    /** Intoarcerea clientului de pe pagina Stripe: sesiunea se citeste direct de la Stripe, nu din adresa paginii. */
    public function confirmReturn(OnlinePayment $payment): OnlinePayment
    {
        if ($payment->isPaid() || ! $payment->stripe_session_id) {
            return $payment;
        }
        $session = $this->fetchSession($payment->stripe_session_id);

        return $session ? $this->applySession($session) ?? $payment->refresh() : $payment;
    }

    /** Verifica semnatura Stripe a webhook-ului (Stripe-Signature: t=...,v1=...), cu toleranta de 5 minute. */
    public static function validSignature(string $payload, ?string $header, string $secret, int $tolerance = 300): bool
    {
        if ($secret === '' || ! $header) {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        if (! $timestamp || abs(time() - $timestamp) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function handleEvent(array $event): void
    {
        $session = $event['data']['object'] ?? [];
        match ($event['type'] ?? '') {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->applySession($session),
            'checkout.session.expired' => $this->closeSession($session, 'expired'),
            'checkout.session.async_payment_failed' => $this->closeSession($session, 'failed'),
            default => null,
        };
    }

    private function paymentFor(array $session): ?OnlinePayment
    {
        $id = (int) ($session['metadata']['payment_id'] ?? $session['client_reference_id'] ?? 0);
        $payment = $id ? OnlinePayment::query()->find($id) : null;

        return $payment && $payment->stripe_session_id === ($session['id'] ?? null) ? $payment : null;
    }

    private function closeSession(array $session, string $status): void
    {
        $payment = $this->paymentFor($session);
        if ($payment && $payment->status === 'pending') {
            $payment->update(['status' => $status]);
        }
    }

    /**
     * Aplica o sesiune platita: o singura data, indiferent cate confirmari sosesc. Daca suma, moneda sau starea
     * contractului nu se potrivesc, banii raman incasati, dar plata se marcheaza „de verificat” pentru personal.
     */
    public function applySession(array $session): ?OnlinePayment
    {
        $payment = $this->paymentFor($session);
        if (! $payment || ($session['payment_status'] ?? '') !== 'paid') {
            return $payment;
        }
        $result = DB::transaction(function () use ($payment, $session) {
            $payment = OnlinePayment::query()->lockForUpdate()->find($payment->id);
            if ($payment->isPaid()) {
                return [$payment, false];
            }
            $payment->forceFill(['paid_at' => now(), 'stripe_payment_intent' => is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null]);
            $problem = null;
            if ((int) ($session['amount_total'] ?? -1) !== (int) round($payment->amount * 100) || strtolower((string) ($session['currency'] ?? '')) !== 'ron') {
                $problem = 'Suma sau moneda plătită nu corespunde plății pornite.';
            } else {
                try {
                    DB::transaction(fn () => $this->fulfil($payment, (string) $session['id']));
                } catch (DomainException $e) {
                    $problem = $e->getMessage();
                }
            }
            $payment->forceFill(['status' => $problem ? 'paid_needs_review' : 'paid', 'review_reason' => $problem])->save();
            Audit::log('payment.online_paid', $payment, $payment->purposeLabel().' plătit online: '.WalletLedger::money((float) $payment->amount).' lei'.($problem ? ' (de verificat: '.$problem.')' : ''),
                null, ['stripe_session' => $session['id'], 'live' => $payment->live_mode], $payment->user_id);

            return [$payment, true];
        });
        [$payment, $fresh] = $result;
        if ($fresh) {
            $this->notifyClinic($payment);
        }

        return $payment;
    }

    private function fulfil(OnlinePayment $payment, string $sessionId): void
    {
        $user = $payment->user()->firstOrFail();
        if ($payment->purpose === OnlinePayment::WALLET) {
            $this->ledger->deposit($user, (float) $payment->amount, null, 'Plată online cu cardul', 'stripe:'.$sessionId, 'Plată online (Stripe)');

            return;
        }
        $contract = Contract::query()->findOrFail($payment->contract_id);
        if ($this->activation->amountFor($contract, (int) $payment->months) !== (int) round((float) $payment->amount)) {
            throw new DomainException('Prețul abonamentului s-a schimbat între timp.');
        }
        $this->activation->validatePayment($contract, (int) $payment->months, null, 'card', $sessionId);
    }

    private function notifyClinic(OnlinePayment $payment): void
    {
        try {
            $payment->loadMissing('user', 'contract');
            $html = 'Plată online cu cardul primită de la <strong>'.e($payment->user->name).'</strong> ('.e($payment->user->email).').<br>'
                .e($payment->description()).': <strong>'.WalletLedger::money((float) $payment->amount).' lei</strong>.<br>'
                .($payment->status === 'paid_needs_review'
                    ? '<strong>De verificat:</strong> '.e((string) $payment->review_reason).' Banii au fost încasați, dar operațiunea nu a fost aplicată automat.<br>'
                    : 'Operațiunea a fost aplicată automat în contul clientului.<br>')
                .'Factura se emite manual; după emitere, numărul ei se trece în admin la <em>Abonamente → Plăți online</em>.';
            $this->notifier->send(Settings::emails('mail.clinic_recipients'), 'HiKeVet - plată online primită, de facturat', $html, 'online_payment', 'online_payment', $payment->id, $payment->user_id);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Platile ramase „in curs” (clientul a inchis pagina, webhook-ul nu a ajuns) se verifica direct la Stripe. */
    public function reconcilePending(): array
    {
        $stats = ['checked' => 0, 'paid' => 0, 'closed' => 0];
        if (! self::configured()) {
            return $stats;
        }
        $pending = OnlinePayment::query()->where('status', 'pending')->whereNotNull('stripe_session_id')->where('created_at', '<', now()->subMinutes(15))->limit(50)->get();
        foreach ($pending as $payment) {
            $stats['checked']++;
            $session = $this->fetchSession($payment->stripe_session_id);
            if (! $session) {
                continue;
            }
            if (($session['payment_status'] ?? '') === 'paid') {
                $this->applySession($session);
                $stats['paid']++;
            } elseif (($session['status'] ?? '') === 'expired') {
                $payment->update(['status' => 'expired']);
                $stats['closed']++;
            }
        }

        return $stats;
    }

    public function recordInvoice(OnlinePayment $payment, string $number, string $date, User $actor): void
    {
        if (! $payment->isPaid()) {
            throw new DomainException('Doar plățile încasate se facturează.');
        }
        $payment->update(['invoice_number' => mb_substr(trim($number), 0, 60), 'invoice_date' => $date, 'invoice_recorded_by' => $actor->id, 'invoice_recorded_at' => now()]);
        Audit::log('payment.invoice_recorded', $payment, 'Factura '.$payment->invoice_number.' din '.$date.' pentru plata online #'.$payment->id, null, null, $payment->user_id);
    }
}
