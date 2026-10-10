<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use FinityLabs\FinMail\FinMailPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The agency view: manage sub-accounts (client businesses), team members and
 * agency-wide email templates. Only users flagged as agency admins can enter.
 */
class AgencyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('agency')
            ->path('agency')
            ->brandName(fn () => config('app.name').' Agency')
            ->login()
            ->colors([
                'primary' => Color::Violet,
            ])
            ->viteTheme('resources/css/filament/app/theme.css')
            ->navigationItems([
                NavigationItem::make('Switch to sub-account')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(fn () => url('/app'))
                    ->sort(1000),
            ])
            ->discoverResources(in: app_path('Filament/Agency/Resources'), for: 'App\Filament\Agency\Resources')
            ->discoverPages(in: app_path('Filament/Agency/Pages'), for: 'App\Filament\Agency\Pages')
            ->discoverWidgets(in: app_path('Filament/Agency/Widgets'), for: 'App\Filament\Agency\Widgets')
            ->plugins([
                FinMailPlugin::make(),
            ])
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
