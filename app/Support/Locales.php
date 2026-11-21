<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Limbile site-ului public. Romana este limba de baza si sta la radacina (adresele de pana acum raman neschimbate);
 * engleza si maghiara au prefix: /en/... si /hu/... Adminul ramane in romana.
 */
class Locales
{
    public const DEFAULT = 'ro';

    public const ALL = [
        'ro' => ['name' => 'Română', 'short' => 'RO', 'hreflang' => 'ro', 'og' => 'ro_RO', 'intl' => 'ro-RO'],
        'en' => ['name' => 'English', 'short' => 'EN', 'hreflang' => 'en', 'og' => 'en_GB', 'intl' => 'en-GB'],
        'hu' => ['name' => 'Magyar', 'short' => 'HU', 'hreflang' => 'hu', 'og' => 'hu_HU', 'intl' => 'hu-HU'],
    ];

    /** Zonele care nu se traduc si nu primesc prefix. */
    public const UNLOCALIZED = ['admin', 'livewire', 'install', 'stripe', 'build', 'media', 'storage', 'up', 'fonts', 'filament', 'uploads'];

    public static function current(): string
    {
        $locale = app()->getLocale();

        return isset(self::ALL[$locale]) ? $locale : self::DEFAULT;
    }

    public static function isDefault(?string $locale = null): bool
    {
        return ($locale ?? self::current()) === self::DEFAULT;
    }

    /** Prefixul de adresa pentru limba data ('' pentru romana). */
    public static function prefix(?string $locale = null): string
    {
        $locale ??= self::current();

        return $locale === self::DEFAULT ? '' : '/'.$locale;
    }

    /**
     * Ruleaza codul in romana (contracte PDF, emailuri catre clinica): documentele juridice si notificarile
     * raman in limba de baza chiar daca vizitatorul foloseste site-ul in engleza sau maghiara.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function asDefault(callable $callback): mixed
    {
        $previous = app()->getLocale();
        if ($previous === self::DEFAULT) {
            return $callback();
        }
        app()->setLocale(self::DEFAULT);
        \Carbon\Carbon::setLocale(self::DEFAULT);
        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
            \Carbon\Carbon::setLocale($previous);
        }
    }

    public static function intl(): string
    {
        return self::ALL[self::current()]['intl'];
    }

    /** Adresa paginii curente in alta limba (aceeasi cale, alt prefix). */
    public static function switchUrl(string $locale, ?Request $request = null): string
    {
        $request ??= request();
        $path = '/'.ltrim($request->getPathInfo(), '/');
        $query = $request->getQueryString();

        return $request->root().self::prefix($locale).($path === '/' ? '/' : $path).($query ? '?'.$query : '');
    }

    /** Desparte primul segment de limba dintr-o cale: ['en', '/servicii/'] sau [null, cale]. */
    public static function split(string $path): array
    {
        $trimmed = ltrim($path, '/');
        $first = strtok($trimmed, '/') ?: '';
        if ($first !== self::DEFAULT && isset(self::ALL[$first])) {
            $rest = substr($trimmed, strlen($first));
            $rest = $rest === '' ? '/' : $rest;
            $second = strtok(ltrim($rest, '/'), '/') ?: '';
            if (! in_array($second, self::UNLOCALIZED, true)) {
                return [$first, $rest];
            }
        }

        return [null, $path];
    }
}
