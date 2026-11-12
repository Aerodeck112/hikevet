<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Jurnal de audit: se poate doar adauga. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["before" => "array", "after" => "array", "created_at" => "datetime"];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException("Jurnalul de audit nu poate fi modificat."));
        static::deleting(fn () => throw new LogicException("Jurnalul de audit nu poate fi sters."));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, "actor_id");
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, "client_id");
    }
}
