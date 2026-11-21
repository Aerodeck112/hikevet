<?php

namespace App\Support;

/**
 * Un singur sistem accepta programari la un moment dat. Implicit programarile se fac pe site,
 * ca cereri pe care clinica le confirma; Digitail ramane doar ca optiune din Setari.
 */
class Booking
{
    public static function mode(): string
    {
        return Settings::get('booking.mode') === 'digitail' ? 'digitail' : 'internal';
    }

    public static function url(): string
    {
        return self::mode() === 'internal' ? site_url('/programare/') : (string) Settings::get('booking.digitail_url');
    }

    public static function isExternal(): bool
    {
        return self::mode() !== 'internal';
    }
}
