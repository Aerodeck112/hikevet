<?php

use App\Http\Controllers\Install\InstallController;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\PublicRedirects;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function () {
            // Instalatorul ruleaza fara sesiune, cookie-uri, CSRF sau limitare prin cache: inainte de instalare
            // nu exista nici cheie, nici tabelele de sesiune si cache. Accesul este protejat de codul de pe disc.
            Route::prefix('install')->group(function () {
                $c = InstallController::class;
                Route::get('/', [$c, 'start']);
                Route::post('/verificari', [$c, 'requirements']);
                Route::post('/configurare', [$c, 'configure']);
                Route::post('/tabele', [$c, 'database']);
                Route::post('/administrator', [$c, 'admin']);
            });
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureInstalled::class);
        $middleware->web(append: [
            SecurityHeaders::class,
            PublicRedirects::class,
        ]);
        $middleware->redirectGuestsTo(fn () => site_url('/acces-cont/'));
        // Stripe isi semneaza singur notificarile (verificate in controller); nu are token CSRF.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        $middleware->redirectUsersTo(fn () => site_url('/panou-cont/'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
