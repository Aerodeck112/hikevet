<?php

namespace App\Models;

use App\Enums\BalanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Rand de registru: se poate doar adauga. Corectiile se fac prin tranzactii inverse. */
class WalletTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["balance_type" => BalanceType::class, "amount_delta" => "decimal:2", "points_delta" => "integer", "created_at" => "datetime"];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException("Tranzactiile din registru nu pot fi modificate."));
        static::deleting(fn () => throw new LogicException("Tranzactiile din registru nu pot fi sterse."));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, "actor_id");
    }
}
