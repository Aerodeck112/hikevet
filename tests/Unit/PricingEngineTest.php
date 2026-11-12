<?php

namespace Tests\Unit;

use App\Services\Subscriptions\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Valorile asteptate sunt cele din tier_from_weight() si apply_mods() ale simulatorului 2.7.0. */
class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
    }

    public static function tiers(): array
    {
        return [
            // specie, greutate, categorie, lunar, 6 luni, 12 luni, total normal, total abonament
            ['dog', 1.25, '1.25-2.5 kg', 229, 1237, 2200, 3810, 2288],
            ['dog', 2.49, '1.25-2.5 kg', 229, 1237, 2200, 3810, 2288],
            ['dog', 2.5, '2.5-5 kg', 231, 1251, 2224, 4110, 2288],
            ['dog', 5, '5-10 kg', 240, 1296, 2304, 4110, 2338],
            ['dog', 12.5, '10-20 kg', 248, 1335, 2376, 4110, 2438],
            ['dog', 20, '20-40 kg', 257, 1388, 2468, 4110, 2484],
            ['dog', 40, '40-60 kg', 265, 1431, 2544, 4110, 2488],
            ['dog', 60, '40-60 kg', 265, 1431, 2544, 4110, 2488],
            ['cat', 0.5, '0.5-2 kg', 216, 1169, 2078, 3800, 2242],
            ['cat', 2, '2-4 kg', 225, 1215, 2160, 3800, 2242],
            ['cat', 4, '4-8 kg', 233, 1260, 2240, 3800, 2386],
            ['cat', 8, 'Peste 8 kg', 237, 1278, 2272, 3800, 2446],
            ['cat', 15, 'Peste 8 kg', 237, 1278, 2272, 3800, 2446],
        ];
    }

    #[DataProvider('tiers')]
    public function test_tier_and_standard_prices(string $species, float $weight, string $label, int $monthly, int $m6, int $m12, int $totalN, int $totalA): void
    {
        $offer = app(PricingEngine::class)->offer($species, $weight);

        $this->assertSame($label, $offer->tier->label);
        $this->assertSame([$monthly, $m6, $m12], [$offer->finalMonthly, $offer->final6m, $offer->final12m]);
        $this->assertSame([$totalN, $totalA], [$offer->totalNormal, $offer->totalSubscription]);
        $this->assertSame(0, $offer->discountPercent);
        $this->assertSame('Primul animal - pret standard', $offer->discountLabel);
        $this->assertCount(15, $offer->services);
    }

    public function test_weight_outside_tiers_has_no_offer(): void
    {
        $engine = app(PricingEngine::class);

        $this->assertNull($engine->offer('dog', 1.24));
        $this->assertNull($engine->offer('dog', 60.01));
        $this->assertNull($engine->offer('cat', 0.49));
        $this->assertNull($engine->offer('rabbit', 3));
    }

    public static function multiPet(): array
    {
        return [
            // animale existente (categorii), specie + greutate noua, procent, lunar final, eticheta
            'al doilea, alta categorie' => [['2.5-5 kg'], 'dog', 12.5, 30, 174, 935, 1663],
            'al doilea, aceeasi categorie' => [['10-20 kg'], 'dog', 12.5, 100, 0, 0, 0],
            'al treilea, alta categorie' => [['2.5-5 kg', '5-10 kg'], 'dog', 12.5, 40, 149, 801, 1426],
            'al treilea, aceeasi cu primul' => [['10-20 kg', '5-10 kg'], 'dog', 12.5, 100, 0, 0, 0],
            'al patrulea, alta categorie' => [['2.5-5 kg', '5-10 kg', '20-40 kg'], 'dog', 12.5, 50, 124, 668, 1188],
            'al cincilea ramane la 50' => [['2.5-5 kg', '5-10 kg', '20-40 kg', '40-60 kg'], 'dog', 12.5, 50, 124, 668, 1188],
            'pisica dupa caine' => [['10-20 kg'], 'cat', 3, 30, 158, 851, 1512],
            'eticheta cu majuscule diferite' => [['PESTE 8 KG'], 'cat', 9, 100, 0, 0, 0],
        ];
    }

    #[DataProvider('multiPet')]
    public function test_multi_pet_discount(array $existing, string $species, float $weight, int $percent, int $monthly, int $m6, int $m12): void
    {
        $offer = app(PricingEngine::class)->offer($species, $weight, $existing);

        $this->assertSame($percent, $offer->discountPercent);
        $this->assertSame([$monthly, $m6, $m12], [$offer->finalMonthly, $offer->final6m, $offer->final12m]);
        $this->assertSame(count($existing) + 1, $offer->petOrdinal);
    }

    public function test_tier_specific_service_adjustments(): void
    {
        $services = collect(app(PricingEngine::class)->offer('dog', 45)->services)->keyBy('name');

        // 40-60 kg: +50 la radiografie, ecografie, deplasare si vidare glande (apply_mods).
        $this->assertSame(420, $services['Expunere radiografica x8']['abon']);
        $this->assertSame(350, $services['Ecografie abdominala x4']['abon']);
        $this->assertSame(250, $services['Deplasare la domiciliu x2']['abon']);
        $this->assertSame(130, $services['Vidare glande perianale x4']['abon']);
        $this->assertSame(200, $services['Consultatie generala x4']['abon']);
    }

    public function test_no_deworming_service_is_included(): void
    {
        foreach (['dog' => 12.0, 'cat' => 3.0] as $species => $weight) {
            foreach (app(PricingEngine::class)->offer($species, $weight)->services as $service) {
                $this->assertStringNotContainsStringIgnoringCase('deparazit', $service['name']);
            }
        }
    }
}
