<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopupRequest extends Model
{
    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["amount" => "decimal:2", "handled_at" => "datetime"];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, "user_id");
    }
}
