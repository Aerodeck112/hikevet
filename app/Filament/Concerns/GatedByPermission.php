<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/** Leaga o resursa din admin de o permisiune. Resursele cu $readOnly = true nu pot crea, edita sau sterge. */
trait GatedByPermission
{
    abstract public static function permission(): string;

    protected static function writable(): bool
    {
        return true;
    }

    private static function allowed(): bool
    {
        return (bool) auth()->user()?->can(static::permission());
    }

    public static function canAccess(): bool
    {
        return static::allowed();
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }

    public static function canView(Model $record): bool
    {
        return static::allowed();
    }

    public static function canCreate(): bool
    {
        return static::allowed() && static::writable();
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowed() && static::writable();
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed() && static::writable();
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed() && static::writable();
    }
}
