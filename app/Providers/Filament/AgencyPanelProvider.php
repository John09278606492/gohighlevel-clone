<?php

namespace App\Providers\Filament;

use App\Models\SubAccount;
use Filament\Actions\Action;
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
                NavigationItem::make('back-to-sub-account')
                    ->label(fn () => ($last = static::lastSubAccount()) ? "Back to {$last->name}" : 'Open a sub-account')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->url(fn () => static::lastSubAccountUrl())
                    ->sort(-100),
            ])
            ->userMenuItems([
                Action::make('backToSubAccount')
                    ->label('Back to sub-account')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->url(fn () => static::lastSubAccountUrl()),
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

    protected static function lastSubAccount(): ?SubAccount
    {
        $id = session('last_sub_account_id');

        return ($id ? SubAccount::find($id) : null) ?? SubAccount::query()->orderBy('name')->first();
    }

    protected static function lastSubAccountUrl(): string
    {
        $last = static::lastSubAccount();

        return $last ? url('/app/'.$last->slug) : url('/app');
    }
}
