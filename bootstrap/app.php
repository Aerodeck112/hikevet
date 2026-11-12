<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function () {
            // Instalatorul ruleaza fara sesiune, cookie-uri sau CSRF: inainte de instalare nu exista cheie si baza de date.
            \Illuminate\Support\Facades\Route::prefix('install')->middleware('throttle:30,1')->group(function () {
                $c = \App\Http\Controllers\Install\InstallController::class;
                \Illuminate\Support\Facades\Route::get('/', [$c, 'start']);
                \Illuminate\Support\Facades\Route::post('/verificari', [$c, 'requirements']);
                \Illuminate\Support\Facades\Route::post('/configurare', [$c, 'configure']);
                \Illuminate\Support\Facades\Route::post('/tabele', [$c, 'database']);
                \Illuminate\Support\Facades\Route::post('/administrator', [$c, 'admin']);
            });
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\EnsureInstalled::class);
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\PublicRedirects::class,
        ]);
        $middleware->redirectGuestsTo(fn () => site_url('/acces-cont/'));
        $middleware->redirectUsersTo(fn () => site_url('/panou-cont/'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
