<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Harta de redirecturi este tinuta in cache de middleware; orice modificare o goleste.
        static::saved(fn () => Cache::forget('redirects.map'));
        static::deleted(fn () => Cache::forget('redirects.map'));
    }
}
