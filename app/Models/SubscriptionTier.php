<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionTier extends Model
{
    public $timestamps = false;

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["min_kg" => "float", "max_kg" => "float", "max_inclusive" => "boolean"];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(SubscriptionTierService::class)->orderBy("sort");
    }

    public function contains(float $weight): bool
    {
        if ($weight < $this->min_kg) {
            return false;
        }

        return $this->max_inclusive ? $weight <= $this->max_kg : $weight < $this->max_kg;
    }
}
