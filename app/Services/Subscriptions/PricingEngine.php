<?php

namespace App\Services\Subscriptions;

use App\Models\PriceList;
use App\Models\SubscriptionTier;
use App\Models\User;

/**
 * Motorul de preturi pentru abonamente.
 *
 * Portare 1:1 a functiilor tier_from_weight(), server_discount_info(), base_services() si
 * apply_mods() din hikevet-abonament-simulator 2.7.0. Valorile vin din lista de preturi activa
 * (tabele versionate), nu din cod. Calculul se face numai pe server.
 */
class PricingEngine
{
    public const SPECIES = ['dog', 'cat'];

    public const PERIODS = [1, 6, 12];

    public function tierFor(string $species, float $weight, ?PriceList $list = null): ?SubscriptionTier
    {
        if (! in_array($species, self::SPECIES, true)) {
            return null;
        }

        $list ??= PriceList::active();

        return $list->tiers->where('species', $species)->first(fn (SubscriptionTier $tier) => $tier->contains($weight));
    }

    /**
     * @param  list<string>  $existingTierLabels  categoriile animalelor active deja existente in cont, in ordinea adaugarii
     */
    public function offer(string $species, float $weight, array $existingTierLabels = [], ?PriceList $list = null): ?Offer
    {
        $list ??= PriceList::active();
        $tier = $this->tierFor($species, $weight, $list);

        if ($tier === null) {
            return null;
        }

        $rules = $list->multi_pet_rules;
        $ordinal = count($existingTierLabels) + 1;
        $normalized = mb_strtolower(trim($tier->label));
        $matched = $ordinal > 1 && in_array($normalized, array_map(fn ($l) => mb_strtolower(trim((string) $l)), $existingTierLabels), true);

        $percent = 0;
        if ($ordinal > 1) {
            $percent = $matched
                ? (int) $rules['same_tier_percent']
                : (int) ($rules['different_tier_percent'][(string) min($ordinal, (int) $rules['max_ordinal'])] ?? 0);
        }

        $label = match (true) {
            $ordinal <= 1 => 'Primul animal - pret standard',
            $matched => "Animalul nr. {$ordinal} - {$percent}% reducere, se incadreaza in categoria de kg existenta",
            default => "Animalul nr. {$ordinal} - reducere {$percent}% (categorie de kg diferita)",
        };

        $factor = max(0, (100 - $percent) / 100);
        $services = $tier->services->map(fn ($s) => [
            'name' => $s->name,
            'display_name' => $s->display_name,
            'normal' => (int) $s->price_normal,
            'abon' => (int) $s->price_subscription,
        ])->values()->all();

        return new Offer(
            tier: $tier,
            petOrdinal: $ordinal,
            discountPercent: $percent,
            matchedExistingTier: $matched,
            discountLabel: $label,
            standardMonthly: $tier->price_monthly,
            standard6m: $tier->price_6m,
            standard12m: $tier->price_12m,
            finalMonthly: (int) round($tier->price_monthly * $factor),
            final6m: (int) round($tier->price_6m * $factor),
            final12m: (int) round($tier->price_12m * $factor),
            services: $services,
            totalNormal: $tier->total_normal,
            totalSubscription: $tier->total_subscription,
        );
    }

    /** @return list<string> */
    public function existingTierLabels(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $user->activePets()->pluck('tier_label')->map(fn ($l) => (string) $l)->all();
    }

    /** Limitele de greutate acceptate de formular (hv-form.js): caine 1,25-60 kg, pisica 0,5-70 kg. */
    public function weightBounds(string $species): array
    {
        return $species === 'dog' ? [1.25, 60.0] : [0.5, 70.0];
    }
}
