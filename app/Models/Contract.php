<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $guarded = ["id"];

    protected $hidden = ["payload"];

    protected function casts(): array
    {
        return [
            "status" => ContractStatus::class,
            "payload" => "encrypted:array",
            "legal_consents" => "array",
            "payment_amount" => "decimal:2",
            "signed_at" => "datetime", "valid_until" => "datetime", "paid_at" => "datetime",
            "starts_at" => "datetime", "expires_at" => "datetime", "cancelled_at" => "datetime",
            "expiry_notice_sent_at" => "datetime",
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, "contract_template_id");
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, "validated_by");
    }

    public function history(): HasMany
    {
        return $this->hasMany(ContractStatusHistory::class)->orderBy("id");
    }

    public function priceForPeriod(int $months): int
    {
        return match ($months) {
            12 => $this->price_12m,
            6 => $this->price_6m,
            default => $this->price_monthly,
        };
    }

    public function isActive(): bool
    {
        return $this->status === ContractStatus::Active && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
