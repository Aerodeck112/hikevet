<?php

namespace Tests\Feature;

use App\Enums\BalanceType;
use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionActivation;
use App\Services\Wallet\WalletLedger;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Felia verticala: client + animal + abonament + contract in asteptarea platii + validare la clinica. */
class ContractFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
    }

    public function test_guest_submits_contract_and_gets_account_pet_pdf_and_awaiting_status(): void
    {
        $response = $this->postJson('/abonamente/contract', $this->contractData());

        $response->assertOk()->assertJson(['duplicate' => false, 'account_created' => true]);
        $contract = Contract::query()->sole();
        $user = User::query()->where('email', 'popescu.test@example.com')->sole();

        $this->assertSame(ContractStatus::AwaitingClinic, $contract->status);
        $this->assertSame('10-20 kg', $contract->tier_label);
        $this->assertSame([248, 1335, 2376], [$contract->price_monthly, $contract->price_6m, $contract->price_12m]);
        $this->assertSame('248.00', $contract->payment_amount);
        $this->assertMatchesRegularExpression('/^HV-AB-\d{4}-00001$/', $contract->number);
        $this->assertTrue($user->hasRole(Role::Client->value));
        $this->assertTrue($user->must_set_password);
        $this->assertSame('Rex', $user->pets()->sole()->name);
        $this->assertSame('10-20 kg', $user->pets()->sole()->tier_label);
        $this->assertTrue($contract->valid_until->isSameDay(now()->addDays(5)));

        // PDF-ul si semnatura sunt pe discul privat, cu amprenta SHA-256 salvata.
        Storage::disk('private')->assertExists($contract->pdf_path);
        Storage::disk('private')->assertExists($contract->signature_path);
        $pdf = Storage::disk('private')->get($contract->pdf_path);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(hash('sha256', $pdf), $contract->pdf_sha256);
        $this->assertSame(array_keys($contract->legal_consents['accepted']), ['contract', 'annex', 'terms', 'guide', 'privacy', 'not_insurance']);
    }

    public function test_cnp_is_encrypted_at_rest_and_never_reaches_the_audit_log(): void
    {
        $this->postJson('/abonamente/contract', $this->contractData())->assertOk();

        $raw = DB::table('contracts')->value('payload');
        $this->assertStringNotContainsString('1900101000000', $raw);
        $this->assertSame('1900101000000', Contract::query()->sole()->payload['owner_cnp']);
        $this->assertStringNotContainsString('1900101000000', AuditLog::all()->toJson());
    }

    public function test_prices_sent_by_the_browser_are_ignored(): void
    {
        $this->postJson('/abonamente/contract', $this->contractData(['price_monthly' => 1, 'price_12m' => 1, 'tier_label' => '1.25-2.5 kg', 'discount_percent' => 100]))->assertOk();

        $contract = Contract::query()->sole();
        $this->assertSame(248, $contract->price_monthly);
        $this->assertSame(0, $contract->discount_percent);
    }

    public function test_same_submission_token_does_not_create_a_second_contract(): void
    {
        $data = $this->contractData(['submission_token' => 'token-fix-123456']);
        $this->postJson('/abonamente/contract', $data)->assertOk();
        $this->postJson('/abonamente/contract', $data)->assertOk()->assertJson(['duplicate' => true]);

        $this->assertSame(1, Contract::count());
    }

    public function test_guest_cannot_attach_a_contract_or_get_a_discount_using_someone_elses_email(): void
    {
        $existing = $this->client();
        $existing->pets()->create(['name' => 'Bella', 'species' => 'dog', 'tier_label' => '10-20 kg']);

        $this->postJson('/abonamente/contract', $this->contractData(['owner_email' => $existing->email]))
            ->assertStatus(422)->assertJsonValidationErrors('owner_email');
        $this->assertSame(0, Contract::count());
    }

    public function test_logged_in_client_gets_multi_pet_discount_for_second_and_matching_pets(): void
    {
        $this->postJson('/abonamente/contract', $this->contractData())->assertOk();
        $user = User::query()->where('email', 'popescu.test@example.com')->sole();

        // Al doilea animal, categorie diferita: 30%.
        $this->actingAs($user)->postJson('/abonamente/contract', $this->contractData(['pet_name' => 'Miți', 'species' => 'cat', 'weight' => 3, 'payment_period_months' => 12]))->assertOk();
        $second = Contract::query()->where('pet_name', 'Miți')->sole();
        $this->assertSame([30, 2, 158, 1512], [$second->discount_percent, $second->pet_ordinal, $second->price_monthly, $second->price_12m]);
        $this->assertSame('1512.00', $second->payment_amount);

        // Al treilea animal, aceeasi categorie cu primul: 100%.
        $this->actingAs($user)->postJson('/abonamente/contract', $this->contractData(['pet_name' => 'Max', 'weight' => 15]))->assertOk();
        $third = Contract::query()->where('pet_name', 'Max')->sole();
        $this->assertSame([100, 3, 0], [$third->discount_percent, $third->pet_ordinal, $third->price_monthly]);
        $this->assertSame(3, $user->activePets()->count());
    }

    public function test_validation_rejects_missing_legal_consent_bad_cnp_and_missing_signature(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->postJson('/abonamente/contract', $this->contractData(['legal' => ['contract' => true, 'annex' => true, 'terms' => true, 'guide' => true, 'privacy' => true, 'not_insurance' => false]]))
            ->assertStatus(422)->assertJsonValidationErrors('legal.not_insurance');
        $this->postJson('/abonamente/contract', $this->contractData(['owner_cnp' => '123']))->assertStatus(422)->assertJsonValidationErrors('owner_cnp');
        $this->postJson('/abonamente/contract', $this->contractData(['signature' => '']))->assertStatus(422)->assertJsonValidationErrors('signature');
        $this->postJson('/abonamente/contract', $this->contractData(['signature' => 'data:image/png;base64,'.base64_encode('nu este o imagine')]))->assertStatus(422);
        $this->postJson('/abonamente/contract', $this->contractData(['weight' => 65]))->assertStatus(422);
        $this->assertSame(0, Contract::count());
    }

    public function test_clinic_validates_payment_points_ignore_period_discount_and_subscription_becomes_active(): void
    {
        $this->postJson('/abonamente/contract', $this->contractData(['payment_period_months' => 12]))->assertOk();
        $contract = Contract::query()->sole();
        $doctor = $this->staff();

        app(SubscriptionActivation::class)->validatePayment($contract, 12, $doctor);
        $contract->refresh();
        $ledger = app(WalletLedger::class);

        $this->assertSame(ContractStatus::Active, $contract->status);
        $this->assertSame('2376.00', $contract->payment_amount);
        $this->assertTrue($contract->expires_at->isSameDay(now()->addMonths(12)));
        // Suma platita are reducerea de 12 luni (2376), punctele nu: 248 x 12 = 2976.
        $this->assertSame(2976, $ledger->points($contract->client));
        $this->assertSame(2376.0, $ledger->amount($contract->client, BalanceType::Subscription));
        $this->assertTrue($ledger->hasActiveSubscription($contract->client));
        $this->assertSame(['awaiting_clinic', 'active'], $contract->history()->pluck('to_status')->all());
    }

    public function test_payment_cannot_be_validated_twice_but_renewal_near_expiry_extends_from_expiry(): void
    {
        $this->postJson('/abonamente/contract', $this->contractData())->assertOk();
        $contract = Contract::query()->sole();
        $activation = app(SubscriptionActivation::class);
        $doctor = $this->staff();

        $activation->validatePayment($contract, 1, $doctor);
        try {
            $activation->validatePayment($contract->refresh(), 1, $doctor);
            $this->fail('A doua validare ar fi trebuit refuzata.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('deja validată', $e->getMessage());
        }
        $this->assertSame(248, app(WalletLedger::class)->points($contract->client));

        $this->travel(26)->days();
        $expiry = $contract->refresh()->expires_at->copy();
        $activation->validatePayment($contract, 6, $doctor);
        $this->assertTrue($contract->refresh()->expires_at->isSameDay($expiry->addMonths(6)));
        $this->assertSame(248 + 248 * 6, app(WalletLedger::class)->points($contract->client));
    }

    public function test_daily_maintenance_expires_subscriptions_and_lapses_unpaid_offers(): void
    {
        $this->postJson('/abonamente/contract', $this->contractData())->assertOk();
        $paid = Contract::query()->sole();
        $activation = app(SubscriptionActivation::class);
        $activation->validatePayment($paid, 1, $this->staff());

        $this->postJson('/abonamente/contract', $this->contractData(['owner_email' => 'alt.client@example.com', 'pet_name' => 'Lola']))->assertOk();

        $this->travel(27)->days();
        $this->assertSame(1, $activation->runDailyMaintenance()['notices']);
        $this->assertSame(0, $activation->runDailyMaintenance()['notices'], 'Avertizarea se trimite o singura data.');

        $this->travel(6)->days();
        $stats = $activation->runDailyMaintenance();
        $this->assertSame(1, $stats['expired']);
        $this->assertSame(ContractStatus::Expired, $paid->refresh()->status);
        $this->assertSame(ContractStatus::OfferLapsed, Contract::query()->where('pet_name', 'Lola')->sole()->status);
        $this->assertFalse(app(WalletLedger::class)->hasActiveSubscription($paid->client));
    }

    public function test_only_the_owner_can_download_the_contract_pdf(): void
    {
        $this->postJson('/abonamente/contract', $this->contractData())->assertOk();
        $contract = Contract::query()->sole();

        $this->get(route('account.contract.pdf', $contract))->assertRedirect();
        $this->actingAs($this->client())->get(route('account.contract.pdf', $contract))->assertForbidden();
        $this->actingAs($contract->client)->get(route('account.contract.pdf', $contract))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_contract_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/abonamente/contract', $this->contractData(['owner_cnp' => 'x']))->assertStatus(422);
        }
        $this->postJson('/abonamente/contract', $this->contractData(['owner_cnp' => 'x']))->assertStatus(429);
    }

    public function test_offer_endpoint_returns_server_prices(): void
    {
        $this->postJson('/abonamente/oferta', ['species' => 'cat', 'weight' => 5])->assertOk()
            ->assertJsonPath('tier_label', '4-8 kg')->assertJsonPath('final.monthly', 233)->assertJsonPath('total_subscription', 2386);
        $this->postJson('/abonamente/oferta', ['species' => 'dog', 'weight' => 80])->assertStatus(422);
    }
}
