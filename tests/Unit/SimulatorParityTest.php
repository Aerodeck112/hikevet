<?php

namespace Tests\Unit;

use App\Services\Subscriptions\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test de caracterizare: compara motorul nou cu functiile reale din pluginul WordPress
 * (tier_from_weight, base_services, apply_mods, server_discount_info), rulate prin reflexie
 * cu functiile WordPress inlocuite de stub-uri. Se sare daca sursele nu sunt pe disc
 * (source-wordpress/ nu este in Git).
 */
class SimulatorParityTest extends TestCase
{
    use RefreshDatabase;

    private const PLUGIN = __DIR__.'/../../source-wordpress/hikevet-abonament-simulator/hikevet-abonament-simulator.php';

    private static ?object $plugin = null;

    /** @var list<array> animalele "din cont" vazute de stub-ul get_user_meta */
    public static array $pets = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(self::PLUGIN)) {
            $this->markTestSkipped('Sursa pluginului lipseste (source-wordpress/ nu este versionat).');
        }

        $this->seedBusiness();

        if (self::$plugin === null) {
            require_once __DIR__.'/../Support/wp-stubs.php';
            require_once self::PLUGIN;
            self::$plugin = (new \ReflectionClass('HV_AS_PDF'))->newInstanceWithoutConstructor();
        }
    }

    private function plugin(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod(self::$plugin, $method);

        return $ref->invoke(self::$plugin, ...$args);
    }

    public function test_every_weight_step_matches_the_plugin(): void
    {
        $engine = app(PricingEngine::class);
        $checked = 0;

        foreach (['dog' => [1.0, 60.5], 'cat' => [0.3, 70.0]] as $species => [$from, $to]) {
            for ($w = $from; $w <= $to + 0.0001; $w = round($w + 0.05, 2)) {
                [$min, $max] = $engine->weightBounds($species);
                $expected = ($w >= $min && $w <= $max) ? $this->plugin('tier_from_weight', $species, $w) : null;
                $offer = ($w >= $min && $w <= $max) ? $engine->offer($species, $w) : null;

                if ($expected === null) {
                    $this->assertNull($offer, "$species $w kg nu ar trebui sa aiba oferta");

                    continue;
                }

                $this->assertNotNull($offer, "$species $w kg ar trebui sa aiba oferta");
                $this->assertSame($expected['label'], $offer->tier->label, "$species $w kg");
                $this->assertSame([(int) $expected['monthly'], (int) $expected['m6'], (int) $expected['m12']],
                    [$offer->standardMonthly, $offer->standard6m, $offer->standard12m]);

                [$rows, $totalN, $totalA] = $this->plugin('apply_mods', $species, $expected['label'], $this->plugin('base_services', $species));
                $this->assertSame(array_map(fn ($r) => [$r['name'], (int) $r['normal'], (int) $r['abon']], $rows),
                    array_map(fn ($r) => [$r['name'], $r['normal'], $r['abon']], $offer->services), "$species {$expected['label']}");
                $this->assertSame([(int) $totalN, (int) $totalA], [$offer->totalNormal, $offer->totalSubscription]);
                $checked++;
            }
        }

        $this->assertGreaterThan(2000, $checked);
    }

    public function test_multi_pet_discount_matches_the_plugin_for_all_combinations(): void
    {
        $engine = app(PricingEngine::class);
        $labels = ['1.25-2.5 kg', '2.5-5 kg', '5-10 kg', '10-20 kg', '20-40 kg', '40-60 kg', '0.5-2 kg', '2-4 kg', '4-8 kg', 'Peste 8 kg'];
        $weights = ['dog' => [2.0, 3.0, 7.0, 15.0, 30.0, 50.0], 'cat' => [1.0, 3.0, 6.0, 9.0]];
        $checked = 0;

        // Toate conturile cu 0-4 animale existente (in orice ordine) x toate categoriile noului animal.
        $accounts = [[]];
        foreach ($labels as $a) {
            $accounts[] = [$a];
            foreach (['2.5-5 kg', 'Peste 8 kg', '10-20 kg'] as $b) {
                $accounts[] = [$a, $b];
                $accounts[] = [$b, $a, '5-10 kg'];
                $accounts[] = [$a, $b, '4-8 kg', '20-40 kg'];
            }
        }

        foreach ($accounts as $existing) {
            self::$pets = array_map(fn ($l) => ['tier_label' => $l], $existing);
            foreach ($weights as $species => $list) {
                foreach ($list as $w) {
                    $tier = $this->plugin('tier_from_weight', $species, $w);
                    $expected = $this->plugin('server_discount_info', $tier, 'client@example.com');
                    $offer = $engine->offer($species, $w, $existing);

                    $this->assertSame((int) $expected['ordinal'], $offer->petOrdinal);
                    $this->assertSame((int) $expected['percent'], $offer->discountPercent, json_encode([$existing, $species, $w]));
                    $this->assertSame($expected['label'], $offer->discountLabel);
                    $this->assertSame([$expected['final_monthly'], $expected['final_6m'], $expected['final_12m']],
                        [$offer->finalMonthly, $offer->final6m, $offer->final12m], json_encode([$existing, $species, $w]));
                    $checked++;
                }
            }
        }

        $this->assertGreaterThan(1000, $checked);
    }

    public function test_contract_template_is_identical_to_the_plugin(): void
    {
        $this->assertSame($this->plugin('contract_template_lines'), \App\Models\ContractTemplate::active()->lines);
    }
}
