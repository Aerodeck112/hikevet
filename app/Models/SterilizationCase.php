<?php

namespace App\Models;

use App\Services\Campaign\CaseFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Dosar din campania de sterilizare.
 *
 * `data` este starea curenta (aceleasi chei ca in plugin). `source_row` + `row_sha256` sunt copia neatinsa a
 * randului importat din WordPress si nu se schimba niciodata. Dosarul se modifica doar prin serviciul campaniei
 * (CampaignCases), care scrie fiecare modificare in jurnal; orice alta scriere este refuzata.
 */
class SterilizationCase extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    private static int $writeDepth = 0;

    protected function casts(): array
    {
        return [
            'source_row' => 'array', 'data' => 'array', 'missing_in_source' => 'array',
            'data_programare' => 'date', 'data_prezentare' => 'date', 'data_interventie' => 'date', 'data_fisa' => 'date',
            'data_raportare_primarie' => 'date', 'data_externare' => 'date', 'data_validare' => 'date',
            'source_created_at' => 'datetime', 'source_updated_at' => 'datetime', 'imported_at' => 'datetime',
            'live_created_at' => 'datetime', 'live_updated_at' => 'datetime', 'deleted_at' => 'datetime',
        ];
    }

    /** Singura cale de scriere: serviciul campaniei. */
    public static function writing(callable $callback): mixed
    {
        self::$writeDepth++;
        try {
            return $callback();
        } finally {
            self::$writeDepth--;
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $case) {
            if (self::$writeDepth === 0) {
                throw new LogicException('Dosarele campaniei se modifică doar prin serviciul campaniei.');
            }
            if ($case->exists && $case->isDirty(['source_row', 'row_sha256']) && $case->getOriginal('row_sha256') !== null) {
                throw new LogicException('Rândul importat din WordPress este dovadă și nu se modifică.');
            }
        });
        static::deleting(fn () => throw new LogicException('Dosarele nu se șterg fizic; se marchează ca șterse, cu motiv.'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SterilizationDocument::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(SterilizationAppointment::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(SterilizationCaseChange::class)->latest('id');
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }

    public function isFinalized(): bool
    {
        return in_array($this->status_interventie, ['Finalizat', 'Operat'], true);
    }

    /** Starea curenta completa a dosarului, cu valorile implicite din plugin. */
    public function row(): array
    {
        return ['id' => $this->id] + CaseFields::normalize($this->data ?? $this->source_row ?? []);
    }

    public function value(string $field): mixed
    {
        return $this->row()[$field] ?? null;
    }

    /** Valoarea originala a unei coloane, exact cum era in sursa WordPress. */
    public function source(string $column): mixed
    {
        return $this->source_row[$column] ?? null;
    }

    public function isImported(): bool
    {
        return $this->source_row !== null;
    }
}
