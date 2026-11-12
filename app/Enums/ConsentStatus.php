<?php

namespace App\Enums;

enum ConsentStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'În așteptarea semnăturii',
            self::Signed => 'Semnat de client',
            self::Cancelled => 'Anulat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Signed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
