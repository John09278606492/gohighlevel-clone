<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\AppointmentsCalendar;
use App\Models\Calendar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CalendarView extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Calendars';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Calendar';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'calendar';

    protected string $view = 'filament.app.pages.calendar-view';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bookingLinks')
                ->label('Booking links')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalSubmitAction(false)
                ->modalContent(fn () => view('filament.app.pages.booking-links', [
                    'calendars' => Calendar::where('sub_account_id', Filament::getTenant()?->getKey())->where('is_active', true)->get(),
                ])),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [AppointmentsCalendar::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
