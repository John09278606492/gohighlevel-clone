<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Tenancy\EditSubAccountProfile;
use App\Filament\App\Pages\Tenancy\RegisterSubAccount;
use App\Flow\CrmNodes;
use App\Support\Branding;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\SubAccount;
use App\PageBuilder\Shortcodes;
use CarlJanzell\FilamentPageBuilder\FilamentPageBuilderPlugin;
use App\Http\Middleware\RememberSubAccount;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Packstub\Flow\FlowPlugin;
use Packstub\FormBuilder\FormBuilderPlugin;
use Relaticle\Flowforge\FlowforgePlugin;

/**
 * The sub-account workspace (GoHighLevel's "location" view). Every record here is
 * scoped to the current sub-account, which is the Filament tenant.
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->brandName(fn () => Branding::name())
            ->brandLogo(fn () => Branding::logoUrl())
            ->favicon(fn () => Branding::faviconUrl())
            ->login()
            ->registration()
            ->passwordReset()
            ->profile()
            ->colors(fn () => ['primary' => Branding::primary(Color::Indigo)])
            ->viteTheme('resources/css/filament/app/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->tenant(SubAccount::class, slugAttribute: 'slug', ownershipRelationship: 'subAccount')
            ->tenantRegistration(RegisterSubAccount::class)
            ->tenantProfile(EditSubAccountProfile::class)
            ->tenantMenuItems([])
            ->navigationGroups([
                NavigationGroup::make('CRM'),
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Calendars'),
                NavigationGroup::make('Marketing'),
                NavigationGroup::make('Payments'),
                NavigationGroup::make('Sites'),
                NavigationGroup::make('Automation'),
                NavigationGroup::make('Reporting'),
                NavigationGroup::make('Settings')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Agency view')
                    ->icon('heroicon-o-building-office-2')
                    ->url(fn () => url('/agency'))
                    ->visible(fn () => (bool) auth()->user()?->is_agency_admin)
                    ->sort(1000),
            ])
            ->userMenuItems([
                Action::make('agencyView')
                    ->label('Agency view (all sub-accounts)')
                    ->icon('heroicon-o-building-office-2')
                    ->url(fn () => url('/agency'))
                    ->visible(fn () => (bool) auth()->user()?->is_agency_admin),
            ])
            ->tenantMiddleware([RememberSubAccount::class], isPersistent: true)
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->plugins([
                FlowforgePlugin::make(),
                FlowPlugin::make()
                    ->navigationGroup('Automation')
                    ->models([Contact::class, Opportunity::class, Appointment::class])
                    ->templates([resource_path('flow-templates')])
                    ->triggers(CrmNodes::TRIGGERS)
                    ->actions(CrmNodes::ACTIONS),
                FormBuilderPlugin::make()
                    ->navigationGroup('Sites'),
                FilamentPageBuilderPlugin::make()
                    ->recordModel(\App\Models\Page::class)
                    ->blocksAttribute('blocks')
                    ->shortcode('form', [Shortcodes::class, 'form'], 'Embeds one of your forms.', '[form slug="contact-us"]')
                    ->shortcode('booking', [Shortcodes::class, 'booking'], 'Embeds a booking calendar.', '[booking calendar="discovery-call"]')
                    ->shortcode('next_step', [Shortcodes::class, 'nextStep'], 'A button to the next funnel step.', '[next_step label="Continue"]')
                    ->shortcode('reviews', [Shortcodes::class, 'reviews'], 'Shows your best public reviews.', '[reviews limit="6" min="4"]')
                    ->shortcode('chat', [Shortcodes::class, 'chat'], 'Adds the chat widget to this page.', '[chat]'),
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
