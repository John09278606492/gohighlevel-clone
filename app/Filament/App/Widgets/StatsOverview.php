<?php

namespace App\Filament\App\Widgets;

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Opportunity;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant()?->getKey();

        $newLeads = fn ($from, $to) => Contact::where('sub_account_id', $tenant)->whereBetween('created_at', [$from, $to])->count();
        $thisMonth = $newLeads(now()->startOfMonth(), now());
        $lastMonth = $newLeads(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow());

        $trend = collect(range(13, 0))
            ->map(fn ($d) => Contact::where('sub_account_id', $tenant)->whereDate('created_at', now()->subDays($d))->count())
            ->all();

        $open = Opportunity::where('sub_account_id', $tenant)->where('status', 'open');
        $won = Opportunity::where('sub_account_id', $tenant)->where('status', 'won')->where('updated_at', '>=', now()->startOfMonth());
        $closed = Opportunity::where('sub_account_id', $tenant)->whereIn('status', ['won', 'lost'])->count();
        $winRate = $closed ? round(Opportunity::where('sub_account_id', $tenant)->where('status', 'won')->count() / $closed * 100) : 0;

        $upcoming = Appointment::where('sub_account_id', $tenant)
            ->whereBetween('starts_at', [now(), now()->addDays(7)])
            ->where('status', '!=', 'cancelled')
            ->count();

        return [
            Stat::make('New leads this month', $thisMonth)
                ->description(($thisMonth >= $lastMonth ? '+' : '').($thisMonth - $lastMonth).' vs last month')
                ->descriptionIcon($thisMonth >= $lastMonth ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($thisMonth >= $lastMonth ? 'success' : 'danger')
                ->chart($trend),
            Stat::make('Open pipeline value', '$'.number_format((float) (clone $open)->sum('value')))
                ->description((clone $open)->count().' open opportunities'),
            Stat::make('Won this month', '$'.number_format((float) $won->sum('value')))
                ->description("Win rate {$winRate}%")
                ->color('success'),
            Stat::make('Appointments next 7 days', $upcoming)
                ->descriptionIcon('heroicon-m-calendar-days'),
        ];
    }
}
