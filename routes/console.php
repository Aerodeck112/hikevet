<?php

use App\Services\Campaign\CityExports;
use App\Services\Payments\StripeCheckout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Un singur cron in cPanel, in fiecare minut: php artisan schedule:run (vezi INSTALL_CPANEL.md).
Schedule::command('hikevet:daily')->dailyAt('07:30')->withoutOverlapping();
Schedule::command('hikevet:backup --label=zilnic --verify')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('auth:clear-resets')->daily();
Schedule::command('hikevet:campaign-emails')->hourly()->withoutOverlapping();
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
Schedule::call(fn () => Cache::forever('hikevet.last_cron_run', now()->toIso8601String()))->everyMinute()->name('hikevet-heartbeat');
// Exporturile pentru Primarie trec prin storage/app/private/tmp; ce ramane dupa un export intrerupt se sterge.
Schedule::call(function () {
    foreach (glob(storage_path('app/private/tmp/hv-*')) ?: [] as $path) {
        if (filemtime($path) < time() - 6 * 3600) {
            is_dir($path) ? CityExports::removeDir($path) : @unlink($path);
        }
    }
})->hourly()->name('hikevet-tmp-cleanup');
// Platile online ramase „in curs” (clientul a inchis pagina, notificarea Stripe nu a ajuns) se verifica la Stripe.
Schedule::call(fn () => app(StripeCheckout::class)->reconcilePending())->everyFifteenMinutes()->name('hikevet-stripe-reconcile')->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [\App\Models\AssistantLog::class]])->dailyAt('03:10');
