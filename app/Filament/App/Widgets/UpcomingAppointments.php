<?php

namespace App\Filament\App\Widgets;

use App\Models\Appointment;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingAppointments extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Upcoming appointments';

    public function table(Table $table): Table
    {
        return $table
            ->query(Appointment::query()
                ->with(['contact', 'calendar'])
                ->where('sub_account_id', Filament::getTenant()?->getKey())
                ->where('starts_at', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->orderBy('starts_at'))
            ->paginated([5])
            ->columns([
                TextColumn::make('starts_at')->label('When')->dateTime('D, M j · g:i A'),
                TextColumn::make('contact.full_name')->label('Contact'),
                TextColumn::make('calendar.name')->label('Calendar'),
                TextColumn::make('status')->badge(),
            ]);
    }
}
