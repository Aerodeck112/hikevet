<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** O informatie scrisa de clinica pentru asistentul AI (program, reguli, raspunsuri la intrebari dese). */
class AssistantFact extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Asistentul afla imediat de orice modificare.
        static::saved(fn () => \App\Services\Assistant\Knowledge::forget());
        static::deleted(fn () => \App\Services\Assistant\Knowledge::forget());
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
