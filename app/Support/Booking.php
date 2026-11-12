<?php

namespace App\Support;

/**
 * Un singur sistem accepta programari la un moment dat. Cat timp modul este "digitail",
 * toate butoanele de programare duc la Digitail, ca pe site-ul actual; sistemul propriu
 * (/programare/) devine public doar dupa comutarea explicita din admin.
 */
class Booking
{
    public static function mode(): string
    {
        return Settings::get('booking.mode') === 'internal' ? 'internal' : 'digitail';
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
