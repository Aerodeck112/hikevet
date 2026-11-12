<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            // 2FA cu aplicatie de autentificare (TOTP) si coduri de recuperare pentru personal.
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()])
            ->brandName('HiKeVet')
            ->brandLogo(asset('media/2023/05/negru-640.webp'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::hex('#21b073'),
                'warning' => Color::hex('#fac44d'),
                'danger' => Color::hex('#d6402a'),
                'info' => Color::hex('#3b82c4'),
                'gray' => Color::Zinc,
            ])
            ->font('Jost Variable', url: asset('fonts/jost.css'), provider: \Filament\FontProviders\LocalFontProvider::class)
            ->globalSearch(true)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->navigationGroups([
                NavigationGroup::make('Clinică'),
                NavigationGroup::make('Abonamente'),
                NavigationGroup::make('Conținut site'),
                NavigationGroup::make('Marketing'),
                NavigationGroup::make('Sistem')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->viteTheme('resources/css/admin.css')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
