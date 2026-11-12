<?php

namespace App\Services\Subscriptions;

use App\Models\SubscriptionTier;

/** Oferta calculata pe server pentru un animal: categorie, reducere multi-animal, preturi si servicii incluse. */
final readonly class Offer
{
    /** @param list<array{name:string,display_name:string,normal:int,abon:int}> $services */
    public function __construct(
        public SubscriptionTier $tier,
        public int $petOrdinal,
        public int $discountPercent,
        public bool $matchedExistingTier,
        public string $discountLabel,
        public int $standardMonthly,
        public int $standard6m,
        public int $standard12m,
        public int $finalMonthly,
        public int $final6m,
        public int $final12m,
        public array $services,
        public int $totalNormal,
        public int $totalSubscription,
    ) {}

    public function priceForPeriod(int $months): int
    {
        return match ($months) {
            12 => $this->final12m,
            6 => $this->final6m,
            default => $this->finalMonthly,
        };
    }

    public function toArray(): array
    {
        return [
            'tier_label' => $this->tier->label,
            'pet_ordinal' => $this->petOrdinal,
            'discount_percent' => $this->discountPercent,
            'discount_label' => $this->discountLabel,
            'matched' => $this->matchedExistingTier,
            'standard' => ['monthly' => $this->standardMonthly, 'm6' => $this->standard6m, 'm12' => $this->standard12m],
            'final' => ['monthly' => $this->finalMonthly, 'm6' => $this->final6m, 'm12' => $this->final12m],
            'services' => $this->services,
            'total_normal' => $this->totalNormal,
            'total_subscription' => $this->totalSubscription,
        ];
    }
}
