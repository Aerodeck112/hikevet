<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Treatment extends Model
{
    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["price_standard" => "decimal:2", "principal_used" => "decimal:2", "bonus_used" => "decimal:2",
            "bonus_blocked" => "boolean", "deleted_at" => "datetime", "restored_at" => "datetime"];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
