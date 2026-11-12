<?php

if (! function_exists('site_url')) {
    /**
     * URL public absolut care pastreaza slash-ul final (url() din Laravel il elimina).
     * Site-ul vechi foloseste peste tot adrese cu slash final, iar acestea raman canonice.
     */
    function site_url(string $path = '/'): string
    {
        $root = app()->runningInConsole() && ! app()->runningUnitTests() ? rtrim((string) config('app.url'), '/') : request()->root();

        return $root.'/'.ltrim($path, '/');
    }
}
