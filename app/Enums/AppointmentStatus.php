<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Cerere nouă',
            self::Confirmed => 'Confirmată',
            self::Completed => 'Finalizată',
            self::NoShow => 'Neprezentat',
            self::Cancelled => 'Anulată',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Confirmed => 'success',
            self::Completed => 'gray',
            self::NoShow, self::Cancelled => 'danger',
        };
    }

    public function holdsSlot(): bool
    {
        return in_array($this, [self::Requested, self::Confirmed, self::Completed], true);
    }
}
