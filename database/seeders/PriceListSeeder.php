<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use App\Models\PriceList;
use Illuminate\Database\Seeder;

/**
 * Lista de preturi si sablonul de contract din hikevet-abonament-simulator 2.7.0.
 *
 * Valorile sunt copiate din tier_from_weight() (liniile 814-836), base_services() (149-186),
 * apply_mods() (188-219) si contract_template_lines() (542-596). Punctele pe utilizare vin din
 * treatment_catalog() al Wallet Core 3.1.0 (database/seeders/data/treatment-catalog.json).
 * Nu sunt estimari si nu se modifica aici: o schimbare de pret inseamna o lista noua.
 */
class PriceListSeeder extends Seeder
{
    public const VERSION = 'wp-simulator-2.7.0';

    public const TIERS = [
        'dog' => [
            ['1.25-2.5 kg', 1.25, 2.5, false, 229, 1237, 2200, 3810, 2288, []],
            ['2.5-5 kg', 2.5, 5, false, 231, 1251, 2224, 4110, 2288, []],
            ['5-10 kg', 5, 10, false, 240, 1296, 2304, 4110, 2338, ['Detartraj x1' => 50]],
            ['10-20 kg', 10, 20, false, 248, 1335, 2376, 4110, 2438, ['Consultatie generala x4' => 50, 'Ecografie abdominala x4' => 50, 'Deplasare la domiciliu x2' => 50]],
            ['20-40 kg', 20, 40, false, 257, 1388, 2468, 4110, 2484, ['Expunere radiografica x8' => 100, 'Consultatie generala x4' => 100]],
            // In sursa limita este 60.0001 exclusiv, adica 60 kg inclusiv.
            ['40-60 kg', 40, 60, true, 265, 1431, 2544, 4110, 2488, ['Expunere radiografica x8' => 50, 'Ecografie abdominala x4' => 50, 'Deplasare la domiciliu x2' => 50, 'Vidare glande perianale x4' => 50]],
        ],
        'cat' => [
            ['0.5-2 kg', 0.5, 2, false, 216, 1169, 2078, 3800, 2242, []],
            ['2-4 kg', 2, 4, false, 225, 1215, 2160, 3800, 2242, []],
            ['4-8 kg', 4, 8, false, 233, 1260, 2240, 3800, 2386, []],
            // In sursa limita superioara este 999; formularul accepta cel mult 70 kg.
            ['Peste 8 kg', 8, 999, false, 237, 1278, 2272, 3800, 2446, []],
        ],
    ];

    /** [nume in contract (fara diacritice), nume afisat, cantitate, normal caine, abonament caine, normal pisica, abonament pisica] */
    public const SERVICES = [
        ['Consultatie generala x4', 'Consultație generală x4', 4, 400, 200, 400, 200],
        ['Ecografie abdominala x4', 'Ecografie abdominală x4', 4, 600, 300, 600, 300],
        ['Expunere radiografica x8', 'Expunere radiografică x8', 8, 720, 370, 720, 370],
        ['Hemoleucograma x1', 'Hemoleucogramă x1', 1, 100, 80, 100, 80],
        ['Biochimie 24 parametri x1', 'Biochimie 24 parametri x1', 1, 350, 250, 350, 250],
        ['Vaccin x1-x3 (in functie de varsta)', 'Vaccin x1-x3 (în funcție de vârstă)', 1, 160, 80, 150, 80],
        ['Detartraj x1', 'Detartraj x1', 1, 350, 250, 350, 250],
        ['Consultatie stomatologica x1', 'Consultație stomatologică x1', 1, 150, 80, 150, 80],
        ['Examen coproparazitologic x2', 'Examen coproparazitologic x2', 2, 80, 40, 80, 40],
        ['Sumar urina x1', 'Sumar urină x1', 1, 70, 35, 70, 35],
        ['Vidare glande perianale x4', 'Vidare glande perianale x4', 4, 200, 80, 200, 60],
        ['Toaletare unghii x4', 'Toaletare unghii x4', 4, 200, 100, 200, 100],
        ['Toaletare urechi x4', 'Toaletare urechi x4', 4, 160, 80, 160, 80],
        ['Video vet x3', 'Video vet x3', 3, 240, 130, 240, 100],
        ['Deplasare la domiciliu x2', 'Deplasare la domiciliu x2', 2, 400, 200, 400, 148],
    ];

    public function run(): void
    {
        if (PriceList::query()->where('version', self::VERSION)->exists()) {
            return;
        }

        $catalog = json_decode(file_get_contents(database_path('seeders/data/treatment-catalog.json')), true);

        $list = PriceList::create([
            'version' => self::VERSION,
            'source' => 'hikevet-abonament-simulator 2.7.0 + hikevet-client-wallet-core 3.1.0',
            'effective_from' => '2026-01-01',
            'is_active' => true,
            'contract_valid_days' => 5,
            'multi_pet_rules' => [
                'same_tier_percent' => 100,
                'different_tier_percent' => ['2' => 30, '3' => 40, '4' => 50],
                'max_ordinal' => 4,
            ],
            'notes' => 'Deparazitarile interne si externe nu sunt incluse si se achita separat (v2.7.0).',
        ]);

        foreach (self::TIERS as $species => $tiers) {
            foreach ($tiers as $sort => [$label, $min, $max, $inclusive, $monthly, $m6, $m12, $totalNormal, $totalSub, $mods]) {
                $tier = $list->tiers()->create([
                    'species' => $species, 'label' => $label, 'min_kg' => $min, 'max_kg' => $max,
                    'max_inclusive' => $inclusive, 'price_monthly' => $monthly, 'price_6m' => $m6, 'price_12m' => $m12,
                    'total_normal' => $totalNormal, 'total_subscription' => $totalSub, 'sort' => $sort,
                ]);

                $points = collect($catalog[$species][$label] ?? [])->keyBy('name');

                foreach (self::SERVICES as $i => [$name, $display, $qty, $dogNormal, $dogSub, $catNormal, $catSub]) {
                    $base = preg_replace('/ x[0-9].*$/', '', $name);
                    $tier->services()->create([
                        'name' => $name,
                        'display_name' => $display,
                        'quantity' => $qty,
                        'quantity_label' => trim(str_replace($base, '', $name)),
                        'price_normal' => $species === 'dog' ? $dogNormal : $catNormal,
                        'price_subscription' => ($species === 'dog' ? $dogSub : $catSub) + ($mods[$name] ?? 0),
                        'points_per_use' => (int) ($points[$base]['subscription_points'] ?? 0),
                        'sort' => $i,
                    ]);
                }
            }
        }

        $lines = json_decode(file_get_contents(database_path('seeders/data/contract-template-2.7.0.json')), true);
        ContractTemplate::firstOrCreate(['version' => 'wp-simulator-2.7.0'], [
            'lines' => $lines,
            'sha256' => hash('sha256', json_encode($lines, JSON_UNESCAPED_UNICODE)),
            'is_active' => true,
            'source' => 'contract_template_lines() din hikevet-abonament-simulator 2.7.0',
        ]);
    }
}
