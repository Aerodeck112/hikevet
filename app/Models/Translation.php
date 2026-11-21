<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** O traducere a unui camp de continut (ex. Service #3, body, en). */
class Translation extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\Translations\ContentTranslations::flush());
        static::deleted(fn () => \App\Services\Translations\ContentTranslations::flush());
    }

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
