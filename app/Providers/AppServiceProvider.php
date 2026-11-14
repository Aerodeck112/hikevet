<?php

namespace App\Providers;

use App\Enums\Role;
use App\Support\Booking;
use App\Support\Settings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
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

        // Pachetele de actualizare pot depasi limita implicita de 12 MB a incarcarilor Livewire; limita reala o da PHP (upload_max_filesize).
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:204800']]);

        Password::defaults(fn () => Password::min(10)->letters()->numbers()->uncompromised(0));

        // Emailul de setare / resetare a parolei, in romana.
        ResetPassword::toMailUsing(function ($user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

            return (new MailMessage)
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
            // Calculat o singura data pe cerere (nu static: un proces poate servi mai multe cereri).
            $clinic = request()->attributes->get('hv.clinic') ?? [
                'name' => Settings::get('clinic.name'), 'phone' => Settings::get('clinic.phone'), 'email' => Settings::get('clinic.email'),
                'address' => Settings::get('clinic.address'), 'city' => Settings::get('clinic.city'), 'tagline' => Settings::get('clinic.tagline'),
                'instagram' => Settings::get('clinic.instagram'), 'facebook' => Settings::get('clinic.facebook'), 'tiktok' => Settings::get('clinic.tiktok'),
                'review_url' => Settings::get('clinic.review_url'), 'booking_url' => Booking::url(),
                'gtm_id' => Settings::get('marketing.gtm_id'), 'ga4_id' => Settings::get('marketing.ga4_id'),
                'verification' => Settings::get('marketing.search_console_verification'),
            ];
            request()->attributes->set('hv.clinic', $clinic);
            $view->with('clinic', $clinic);
        });
    }
}
