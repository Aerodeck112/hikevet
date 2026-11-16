<?php

namespace App\Models;

use App\Services\Legacy\LegacyArchive;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Rand din arhiva WordPress. Doar citire: arhiva se reface numai printr-o noua copie completa a tabelei. */
class LegacyRow extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Arhiva WordPress nu se modifică.'));
        static::deleting(fn () => throw new LogicException('Arhiva WordPress nu se modifică.'));
    }

    /** @return array<string,mixed> */
    public function values(): array
    {
        return app(LegacyArchive::class)->decodeRow($this->data);
    }
}
