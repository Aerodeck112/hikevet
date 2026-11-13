<?php

namespace App\Providers;

use App\Enums\Role;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        // Cand adresa site-ului este HTTPS, toate linkurile generate sunt HTTPS, chiar daca serverul
        // primeste cererea prin HTTP de la un proxy (situatie obisnuita pe hosting shared).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Super Admin are toate permisiunile, inclusiv cele adaugate ulterior.
        Gate::before(fn ($user, $ability) => $user->hasRole(Role::SuperAdmin->value) ? true : null);

        Password::defaults(fn () => Password::min(10)->letters()->numbers()->uncompromised(0));

        // Emailul de setare / resetare a parolei, in romana.
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Setează parola contului HiKeVet')
                ->greeting('Bună, '.$user->name.'!')
                ->line('Ai cerut setarea unei parole noi pentru contul tău HiKeVet.')
                ->action('Setează parola', $url)
                ->line('Linkul este valabil 60 de minute. Dacă nu ai cerut tu acest lucru, ignoră mesajul.')
                ->salutation('Echipa HiKeVet');
        });

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(6)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('forms', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('contract', fn (Request $r) => Limit::perMinute(4)->by($r->ip()));
        RateLimiter::for('consent-code', fn (Request $r) => Limit::perMinute(8)->by($r->ip()));
        RateLimiter::for('booking', fn (Request $r) => Limit::perMinute(6)->by($r->ip()));

        // Datele clinicii sunt disponibile in toate sabloanele publice; se citesc o singura data pe cerere.
        View::composer(['site.*', 'account.*', 'tablet.*', 'booking.*', 'layouts.*', 'partials.*', 'errors::*'], function ($view) {
            static $clinic = null;
            $clinic ??= [
                'name' => Settings::get('clinic.name'), 'phone' => Settings::get('clinic.phone'), 'email' => Settings::get('clinic.email'),
                'address' => Settings::get('clinic.address'), 'city' => Settings::get('clinic.city'), 'tagline' => Settings::get('clinic.tagline'),
                'instagram' => Settings::get('clinic.instagram'), 'facebook' => Settings::get('clinic.facebook'), 'tiktok' => Settings::get('clinic.tiktok'),
                'review_url' => Settings::get('clinic.review_url'), 'booking_url' => \App\Support\Booking::url(),
                'gtm_id' => Settings::get('marketing.gtm_id'), 'ga4_id' => Settings::get('marketing.ga4_id'),
                'verification' => Settings::get('marketing.search_console_verification'),
            ];
            $view->with('clinic', $clinic);
        });
    }
}
