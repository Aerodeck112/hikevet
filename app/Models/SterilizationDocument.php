<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fotografie pacient sau act de identitate, pe discul privat. Un document inlocuit ramane in istoric. */
class SterilizationDocument extends Model
{
    public const PHOTO = 'fotografie_pacient';

    public const ID_CARD = 'act_identitate';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_missing' => 'boolean', 'superseded_at' => 'datetime'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SterilizationCase::class, 'sterilization_case_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
