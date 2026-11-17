<?php

namespace Tests\Feature;

use App\Enums\BalanceType;
use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\OnlinePayment;
use App\Models\User;
use App\Services\Payments\StripeCheckout;
use App\Services\Subscriptions\SubscriptionActivation;
use App\Services\Wallet\WalletLedger;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Plata online cu cardul (Stripe Checkout), cu Stripe simulat: nicio cerere reala, niciun ban. */
class StripePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Contract $contract;

    /** @var array<string,array> sesiunile „create” la Stripe */
    private array $sessions = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->postJson('/abonamente/contract', $this->contractData())->assertOk();
        $this->client = User::query()->where('email', 'popescu.test@example.com')->sole();
        $this->contract = Contract::query()->sole();
        Settings::set('payments.stripe_secret', 'sk_test_sintetic123', true);
        Settings::set('payments.stripe_webhook_secret', 'whsec_sintetic123', true);
        Settings::set('payments.stripe_enabled', true);
        Settings::set('mail.clinic_recipients', ['clinica.test@example.com']);

        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/v1/checkout/sessions')) {
                $id = 'cs_test_'.(count($this->sessions) + 1);
                $this->sessions[$id] = ['id' => $id, 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/'.$id, 'status' => 'open', 'payment_status' => 'unpaid',
                    'amount_total' => (int) $request['line_items'][0]['price_data']['unit_amount'], 'currency' => 'ron',
                    'client_reference_id' => $request['client_reference_id'], 'metadata' => $request['metadata'], 'payment_intent' => 'pi_'.$id, 'request' => $request->data(),
                    'auth' => $request->header('Authorization')[0] ?? '', 'idempotency' => $request->header('Idempotency-Key')[0] ?? ''];

                return Http::response($this->sessions[$id]);
            }
            if ($request->method() === 'GET' && preg_match('#/v1/checkout/sessions/(cs_test_\d+)$#', $request->url(), $m)) {
                return Http::response($this->sessions[$m[1]] ?? ['error' => ['message' => 'No such session']], isset($this->sessions[$m[1]]) ? 200 : 404);
            }

            return Http::response(['error' => ['message' => 'neasteptat']], 500);
        });
    }

    private function pay(string $id): array
    {
        $this->sessions[$id]['payment_status'] = 'paid';
        $this->sessions[$id]['status'] = 'complete';

        return $this->sessions[$id];
    }

    private function webhook(array $event, ?string $secret = 'whsec_sintetic123', ?int $time = null)
    {
        $payload = json_encode($event);
        $time ??= time();
        $signature = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, (string) $secret);

        return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    public function test_subscription_is_paid_online_with_the_contract_price_and_points(): void
    {
        $this->actingAs($this->client)->get('/panou-cont/')->assertOk()->assertSee('Plătește cu cardul');

        $response = $this->actingAs($this->client)->post('/panou-cont/plata/abonament/'.$this->contract->id, ['months' => 6]);
        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $payment = OnlinePayment::query()->sole();
        $expected = app(SubscriptionActivation::class)->amountFor($this->contract, 6);
        $this->assertSame([$expected * 100, 'Bearer sk_test_sintetic123', 'hikevet-payment-'.$payment->id],
            [$this->sessions['cs_test_1']['amount_total'], $this->sessions['cs_test_1']['auth'], $this->sessions['cs_test_1']['idempotency']]);
        $this->assertSame(['pending', false], [$payment->status, $payment->live_mode]);
        $this->assertStringContainsString('/panou-cont/plata/'.$payment->id.'/confirmare?session_id={CHECKOUT_SESSION_ID}', $this->sessions['cs_test_1']['request']['success_url']);

        // Fara plata: intoarcerea nu activeaza nimic.
        $this->actingAs($this->client)->get('/panou-cont/plata/'.$payment->id.'/confirmare?session_id=cs_test_1')->assertRedirect();
        $this->assertSame(ContractStatus::AwaitingClinic, $this->contract->refresh()->status);

        // Plata facuta: intoarcerea o confirma citind sesiunea direct de la Stripe; webhook-ul ulterior nu dubleaza nimic.
        $this->pay('cs_test_1');
        $this->actingAs($this->client)->get('/panou-cont/plata/'.$payment->id.'/confirmare?session_id=cs_test_1')->assertSessionHas('status', 'Plata a fost primită. Abonamentul este activ.');
        $this->webhook(['type' => 'checkout.session.completed', 'data' => ['object' => $this->sessions['cs_test_1']]])->assertOk();

        $this->contract->refresh();
        $this->assertSame([ContractStatus::Active, 'card', 6], [$this->contract->status, $this->contract->payment_method, $this->contract->period_months]);
        $this->assertSame($this->contract->price_monthly * 6, app(WalletLedger::class)->points($this->client));
        $this->assertSame(1, $this->client->walletTransactions()->where('type', 'subscription_paid_card')->count());
        $this->assertSame(['paid', 'pi_cs_test_1'], [$payment->refresh()->status, $payment->stripe_payment_intent]);
        $this->assertDatabaseHas('email_logs', ['context' => 'online_payment']);
    }

    public function test_wallet_top_up_by_card_gets_the_same_bonus_as_at_the_clinic_once(): void
    {
        $this->actingAs($this->client)->post('/panou-cont/plata/wallet', ['amount' => 100])->assertSessionHas('error', 'Wallet-ul poate fi alimentat doar cu abonament activ.');
        app(SubscriptionActivation::class)->validatePayment($this->contract, 1, $this->staff());

        $this->actingAs($this->client)->post('/panou-cont/plata/wallet', ['amount' => 5])->assertSessionHasErrors('amount');
        $this->actingAs($this->client)->post('/panou-cont/plata/wallet', ['amount' => 200])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $session = $this->pay('cs_test_1');

        $this->webhook(['type' => 'checkout.session.completed', 'data' => ['object' => $session]])->assertOk();
        $this->webhook(['type' => 'checkout.session.completed', 'data' => ['object' => $session]])->assertOk();

        $ledger = app(WalletLedger::class);
        $this->assertSame([200.0, 200.0, 100.0], [$ledger->amount($this->client, BalanceType::WalletPrincipal), $ledger->amount($this->client, BalanceType::WalletServiceBonus),
            $ledger->amount($this->client, BalanceType::WalletProductBonus)]);
        $this->assertSame('Plată online (Stripe)', $this->client->walletTransactions()->where('type', 'wallet_deposit_principal')->sole()->actor_name);
    }

    public function test_webhook_requires_a_valid_recent_signature(): void
    {
        app(SubscriptionActivation::class)->validatePayment($this->contract, 1, $this->staff());
        $this->actingAs($this->client)->post('/panou-cont/plata/wallet', ['amount' => 100]);
        $event = ['type' => 'checkout.session.completed', 'data' => ['object' => $this->pay('cs_test_1')]];

        $this->webhook($event, 'whsec_altul')->assertStatus(400);
        $this->webhook($event, time: time() - 3600)->assertStatus(400);
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($event))->assertStatus(400);
        $this->assertSame('pending', OnlinePayment::query()->sole()->status);
        $this->assertSame(0.0, app(WalletLedger::class)->amount($this->client, BalanceType::WalletPrincipal));
    }

    public function test_tampered_or_mismatched_payments_are_kept_for_review_not_applied(): void
    {
        $this->actingAs($this->client)->post('/panou-cont/plata/abonament/'.$this->contract->id, ['months' => 1]);
        $session = $this->pay('cs_test_1');
        $session['amount_total'] = 100; // suma platita difera de cea pornita

        app(StripeCheckout::class)->applySession($session);

        $payment = OnlinePayment::query()->sole();
        $this->assertSame('paid_needs_review', $payment->status);
        $this->assertStringContainsString('Suma', $payment->review_reason);
        $this->assertSame(ContractStatus::AwaitingClinic, $this->contract->refresh()->status);

        // O sesiune a altei plati nu poate confirma plata aceasta.
        $this->actingAs($this->client)->post('/panou-cont/plata/abonament/'.$this->contract->id, ['months' => 1]);
        $other = $this->pay('cs_test_2');
        $other['metadata']['payment_id'] = (string) $payment->id;
        $this->assertSame($payment->id, app(StripeCheckout::class)->applySession($other)?->id ?? $payment->id);
        $this->assertSame('pending', OnlinePayment::query()->latest('id')->first()->status);
    }

    public function test_pending_payments_are_reconciled_when_the_client_never_returns(): void
    {
        app(SubscriptionActivation::class)->validatePayment($this->contract, 1, $this->staff());
        $this->actingAs($this->client)->post('/panou-cont/plata/wallet', ['amount' => 100]);
        $this->actingAs($this->client)->post('/panou-cont/plata/wallet', ['amount' => 50]);
        $this->pay('cs_test_1');
        $this->sessions['cs_test_2']['status'] = 'expired';
        $this->travel(20)->minutes();

        $stats = app(StripeCheckout::class)->reconcilePending();

        $this->assertSame(['checked' => 2, 'paid' => 1, 'closed' => 1], $stats);
        $this->assertSame(['paid', 'expired'], OnlinePayment::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame(100.0, app(WalletLedger::class)->amount($this->client, BalanceType::WalletPrincipal));
    }

    public function test_online_payment_is_hidden_when_disabled_and_only_eligible_contracts_can_be_paid(): void
    {
        Settings::set('payments.stripe_enabled', false);
        $this->actingAs($this->client)->get('/panou-cont/')->assertOk()->assertDontSee('Plătește cu cardul');
        $this->actingAs($this->client)->post('/panou-cont/plata/abonament/'.$this->contract->id, ['months' => 1])->assertSessionHas('error');
        $this->assertSame(0, OnlinePayment::query()->count());

        Settings::set('payments.stripe_enabled', true);
        app(SubscriptionActivation::class)->validatePayment($this->contract, 12, $this->staff());
        // Activ inca un an: reinnoirea online nu este disponibila inca.
        $this->assertFalse(StripeCheckout::canPayOnline($this->contract->refresh()));
        $this->actingAs($this->client)->post('/panou-cont/plata/abonament/'.$this->contract->id, ['months' => 1])->assertSessionHas('error');

        $other = User::factory()->create();
        $this->actingAs($other)->post('/panou-cont/plata/abonament/'.$this->contract->id, ['months' => 1])->assertForbidden();
    }

    public function test_clinic_records_the_manual_invoice_and_sees_what_is_left_to_invoice(): void
    {
        app(SubscriptionActivation::class)->validatePayment($this->contract, 1, $this->staff());
        $this->actingAs($this->client)->post('/panou-cont/plata/wallet', ['amount' => 100]);
        app(StripeCheckout::class)->applySession($this->pay('cs_test_1'));
        $payment = OnlinePayment::query()->sole();
        $reception = $this->staff(Role::Reception);

        $this->assertTrue($payment->needsInvoice());
        $this->actingAs($reception)->get('/admin/online-payments')->assertOk()->assertSee('Popescu')->assertSee('de emis')->assertSee('mod de test');
        app(StripeCheckout::class)->recordInvoice($payment, 'HKV 1024', '2026-11-18', $reception);

        $this->assertSame(['HKV 1024', '2026-11-18', $reception->id], [$payment->refresh()->invoice_number, $payment->invoice_date->toDateString(), $payment->invoice_recorded_by]);
        $this->assertFalse($payment->needsInvoice());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.invoice_recorded']);
        $this->actingAs($this->client)->get('/admin/online-payments')->assertForbidden();
    }

    public function test_settings_store_keys_encrypted_and_refuse_to_enable_without_a_key(): void
    {
        $this->assertTrue(StripeCheckout::configured());
        $this->assertFalse(StripeCheckout::liveMode());
        $this->assertStringNotContainsString('sk_test_sintetic123', (string) \DB::table('settings')->where('key', 'payments.stripe_secret')->value('value'));

        Settings::set('payments.stripe_secret', '', true);
        $this->assertFalse(StripeCheckout::enabled(), 'Fara cheie, plata online nu apare chiar daca este bifata.');
    }
}
