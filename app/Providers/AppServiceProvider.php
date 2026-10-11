<?php

namespace App\Providers;

use App\Flow\CrmNodes;
use App\Models\Appointment;
use App\Models\ContactNote;
use App\Models\Opportunity;
use App\Observers\TimelineObserver;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public booking and funnel pages render outside a panel, so give them the same brand color.
        FilamentColor::register(['primary' => Color::Indigo]);

        CrmNodes::register();

        foreach ([ContactNote::class, Appointment::class, Opportunity::class] as $model) {
            $model::observe(TimelineObserver::class);
        }

        RateLimiter::for('public-forms', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->bearerToken() ?: $request->ip()));
    }
}
