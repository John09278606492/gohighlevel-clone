<?php

namespace App\Filament\Agency\Widgets;

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\SubAccount;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AgencyStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Sub-accounts', SubAccount::where('is_active', true)->count()),
            Stat::make('Contacts (all accounts)', number_format(Contact::count()))
                ->description(Contact::where('created_at', '>=', now()->startOfMonth())->count().' new this month'),
            Stat::make('Open pipeline (all accounts)', '$'.number_format((float) Opportunity::where('status', 'open')->sum('value'))),
            Stat::make('Appointments this week', Appointment::whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->where('status', '!=', 'cancelled')->count()),
        ];
    }
}
