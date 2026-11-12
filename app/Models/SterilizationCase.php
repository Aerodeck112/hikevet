<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Dosar istoric din campania de sterilizare. Read-only dupa import: singura cale de scriere
 * este importatorul, care foloseste withoutEvents().
 */
class SterilizationCase extends Model
{
    public $timestamps = false;

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return [
            "source_row" => "array", "missing_in_source" => "array",
            "data_programare" => "date", "data_prezentare" => "date", "data_interventie" => "date",
            "data_raportare_primarie" => "date", "data_externare" => "date", "data_validare" => "date",
            "source_created_at" => "datetime", "source_updated_at" => "datetime", "imported_at" => "datetime",
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException("Arhiva de sterilizari este read-only."));
        static::deleting(fn () => throw new LogicException("Arhiva de sterilizari este read-only."));
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SterilizationDocument::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(SterilizationAppointment::class);
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }

    public function isFinalized(): bool
    {
        return in_array($this->status_interventie, ["Finalizat", "Operat"], true);
    }

    /** Valoarea originala a unei coloane, exact cum era in sursa. */
    public function source(string $column): mixed
    {
        return $this->source_row[$column] ?? null;
    }
}
