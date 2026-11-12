<?php

namespace Tests\Feature;

use App\Enums\BalanceType;
use App\Models\Contract;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Subscriptions\SubscriptionActivation;
use App\Services\Wallet\WalletLedger;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/** Regulile din hikevet-client-wallet-core 3.1.0. */
class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    private WalletLedger $ledger;

    private User $client;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->ledger = app(WalletLedger::class);
        $this->doctor = $this->staff();
        $this->postJson('/abonamente/contract', $this->contractData())->assertOk();
        $this->client = User::query()->where('email', 'popescu.test@example.com')->sole();
    }

    private function activate(): void
    {
        app(SubscriptionActivation::class)->validatePayment(Contract::query()->sole(), 1, $this->doctor);
    }

    public function test_deposit_requires_active_subscription(): void
    {
        $this->expectException(DomainException::class);
        $this->ledger->deposit($this->client, 100, $this->doctor);
    }

    public function test_deposit_of_100_gives_100_principal_100_service_bonus_50_product_bonus(): void
    {
        $this->activate();
        $this->ledger->deposit($this->client, 100, $this->doctor);
        $b = $this->ledger->balances($this->client);

        $this->assertSame([100.0, 100.0, 50.0], [$b['principal'], $b['service_bonus'], $b['product_bonus']]);
        $this->assertSame(200.0, $b['service_buying_power']);
        $this->assertSame(150.0, $b['product_buying_power']);
        $this->assertSame(248, $b['subscription_points'], 'Punctele de abonament nu se amesteca cu Wallet-ul.');
    }

    public function test_service_purchase_uses_bonus_for_at_most_half_and_product_for_at_most_a_third(): void
    {
        $this->activate();
        $this->ledger->deposit($this->client, 300, $this->doctor);

        $service = $this->ledger->purchase($this->client, 'service', 'Consultație dermatologie', 120, $this->doctor);
        $this->assertSame(['60.00', '60.00'], [$service->principal_used, $service->bonus_used]);

        $product = $this->ledger->purchase($this->client, 'product', 'Hrană dietetică', 90, $this->doctor);
        $this->assertSame(['60.00', '30.00'], [$product->principal_used, $product->bonus_used]);

        $b = $this->ledger->balances($this->client);
        $this->assertSame([180.0, 240.0, 120.0], [$b['principal'], $b['service_bonus'], $b['product_bonus']]);
    }

    public function test_bonus_is_excluded_for_medication_and_external_cost_items_and_on_manual_block(): void
    {
        $this->activate();
        $this->ledger->deposit($this->client, 300, $this->doctor);

        foreach (['Deparazitare internă', 'Medicament antiinflamator', 'Analiză externă tiroidă', 'Examen RMN'] as $name) {
            $t = $this->ledger->purchase($this->client, 'service', $name, 20, $this->doctor);
            $this->assertSame(['20.00', '0.00', true], [$t->principal_used, $t->bonus_used, $t->bonus_blocked], $name);
        }
        $manual = $this->ledger->purchase($this->client, 'service', 'Tuns', 40, $this->doctor, null, '', true);
        $this->assertSame('0.00', $manual->bonus_used);
    }

    public function test_purchase_is_refused_when_principal_is_insufficient_and_nothing_is_written(): void
    {
        $this->activate();
        $this->ledger->deposit($this->client, 50, $this->doctor);
        $before = WalletTransaction::count();

        try {
            $this->ledger->purchase($this->client, 'service', 'Ecografie', 300, $this->doctor);
            $this->fail('Plata ar fi trebuit refuzata.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Sold principal insuficient', $e->getMessage());
        }
        $this->assertSame($before, WalletTransaction::count());
        $this->assertSame(50.0, $this->ledger->balances($this->client)['principal']);
    }

    public function test_subscription_service_consumes_points_only_and_refuses_overdraft_and_deworming(): void
    {
        $this->activate();
        $pet = $this->client->pets()->sole();
        $this->ledger->consumeSubscriptionService($this->client, 'Consultatie generala', 50, $this->doctor, $pet, 50);
        $this->assertSame(198, $this->ledger->points($this->client));

        foreach ([['Ecografie abdominala', 500], ['Deparazitare externa', 10]] as [$name, $points]) {
            try {
                $this->ledger->consumeSubscriptionService($this->client, $name, $points, $this->doctor, $pet);
                $this->fail("$name ar fi trebuit refuzat.");
            } catch (DomainException) {
                $this->assertSame(198, $this->ledger->points($this->client));
            }
        }
    }

    public function test_manual_adjust_requires_full_employee_name_and_never_goes_negative(): void
    {
        $this->activate();

        foreach (['', 'Ana', 'X Y'] as $badName) {
            try {
                $this->ledger->manualAdjust($this->client, BalanceType::Subscription, 10, $badName, $this->doctor);
                $this->fail('Numele incomplet ar fi trebuit refuzat.');
            } catch (DomainException) {
            }
        }
        try {
            $this->ledger->manualAdjust($this->client, BalanceType::Subscription, -9999, 'Ionescu Maria', $this->doctor);
            $this->fail('Soldul negativ ar fi trebuit refuzat.');
        } catch (DomainException) {
        }

        $tx = $this->ledger->manualAdjust($this->client, BalanceType::Subscription, -48, 'Ionescu Maria', $this->doctor, 'corecție');
        $this->assertSame(200, $this->ledger->points($this->client));
        $this->assertStringContainsString('Aplicat de: Ionescu Maria', $tx->note);
        $this->assertSame('Ionescu Maria', $tx->actor_name);
    }

    public function test_deleting_a_treatment_refunds_and_restoring_reapplies_it(): void
    {
        $this->activate();
        $this->ledger->deposit($this->client, 200, $this->doctor);
        $treatment = $this->ledger->purchase($this->client, 'service', 'Consultație', 100, $this->doctor);
        $this->assertSame(150.0, $this->ledger->balances($this->client)['principal']);

        try {
            $this->ledger->deleteTreatment($treatment, '  ', $this->doctor);
            $this->fail('Stergerea fara motiv ar fi trebuit refuzata.');
        } catch (DomainException) {
        }

        $this->ledger->deleteTreatment($treatment, 'introdus greșit', $this->doctor);
        $b = $this->ledger->balances($this->client);
        $this->assertSame([200.0, 200.0], [$b['principal'], $b['service_bonus']]);

        $this->ledger->restoreTreatment($treatment->refresh(), $this->doctor);
        $b = $this->ledger->balances($this->client);
        $this->assertSame([150.0, 150.0], [$b['principal'], $b['service_bonus']]);
        $this->assertNull($treatment->refresh()->deleted_at);
    }

    public function test_ledger_rows_cannot_be_updated_or_deleted(): void
    {
        $this->activate();
        $tx = WalletTransaction::query()->firstOrFail();

        try {
            $tx->update(['points_delta' => 999999]);
            $this->fail('Modificarea ar fi trebuit blocata.');
        } catch (LogicException) {
        }
        $this->expectException(LogicException::class);
        $tx->delete();
    }

    public function test_balance_always_equals_the_sum_of_transactions(): void
    {
        $this->activate();
        $this->ledger->deposit($this->client, 500, $this->doctor);
        $this->ledger->purchase($this->client, 'service', 'Radiografie', 90, $this->doctor);
        $this->ledger->purchase($this->client, 'product', 'Șampon', 45, $this->doctor);
        $this->ledger->manualAdjust($this->client, BalanceType::WalletPrincipal, -10.5, 'Ionescu Maria', $this->doctor);

        foreach (BalanceType::adjustable() as $type) {
            $sum = round((float) WalletTransaction::query()->where('user_id', $this->client->id)->where('balance_type', $type->value)->sum('amount_delta'), 2);
            $this->assertSame($sum, $this->ledger->amount($this->client, $type), $type->value);
            $this->assertGreaterThanOrEqual(0, $sum, $type->value);
        }
    }

    public function test_client_topup_request_does_not_change_the_balance(): void
    {
        $this->activate();
        $this->actingAs($this->client)->post('/panou-cont/wallet/solicitare', ['amount' => 150])->assertRedirect();

        $this->assertDatabaseHas('wallet_topup_requests', ['user_id' => $this->client->id, 'status' => 'pending']);
        $this->assertSame(0.0, $this->ledger->balances($this->client)['principal']);
    }
}
