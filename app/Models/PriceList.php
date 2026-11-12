<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["effective_from" => "date", "is_active" => "boolean", "multi_pet_rules" => "array"];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(SubscriptionTier::class)->orderBy("sort");
    }

    public static function active(): self
    {
        return static::query()->where("is_active", true)->latest("effective_from")->firstOrFail();
    }
}
