<?php

namespace App\Filament\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Leaga o resursa din admin de permisiuni. Toate verificarile Filament (lista, pagini de creare,
 * editare, stergere, actiuni) trec prin getAuthorizationResponse(), deci regula se aplica efectiv
 * la accesarea adresei, nu doar la afisarea butoanelor.
 */
trait GatedByPermission
{
    abstract public static function permission(): string;

    /** Resursele care suprascriu metoda cu false sunt doar pentru citire. */
    protected static function writable(): bool
    {
        return true;
    }

    /**
     * Regula implicita: citirea cere permisiunea resursei; scrierea cere in plus ca resursa sa fie
     * modificabila. Resursele pot suprascrie metoda pentru reguli pe actiune sau pe inregistrare.
     */
    protected static function allows(string $action, ?Model $record = null): bool
    {
        if (! auth()->user()?->can(static::permission())) {
            return false;
        }

        return in_array($action, ['viewAny', 'view'], true) || static::writable();
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;

        return static::allows($action, $record) ? Response::allow() : Response::deny('Nu ai acces la această operațiune.');
    }

    public static function canAccess(): bool
    {
        return static::allows('viewAny');
    }
}
