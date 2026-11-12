<?php

namespace App\Http\Middleware;

use App\Services\Install\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pana la instalare, orice adresa duce la instalator; dupa instalare, instalatorul nu mai exista (404). */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = app(Installer::class)->isInstalled();
        $onInstaller = $request->is('install', 'install/*');

        if (! $installed && ! $onInstaller && ! $request->is('up', 'build/*', 'media/*', 'fonts/*', 'favicon.svg')) {
            return redirect()->away($request->root().'/install');
        }
        abort_if($installed && $onInstaller, 404);

        return $next($request);
    }
}
