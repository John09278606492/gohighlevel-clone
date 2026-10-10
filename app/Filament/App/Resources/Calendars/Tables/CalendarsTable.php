<?php

namespace App\Filament\App\Resources\Calendars\Tables;

use App\Models\Calendar;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CalendarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['appointments' => fn ($q) => $q->where('starts_at', '>=', now())->where('status', '!=', 'cancelled')]))
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->searchable()->weight('medium')->description(fn (Calendar $record) => $record->duration_minutes.' min'),
                TextColumn::make('owner.name')->label('Team member')->placeholder('-'),
                TextColumn::make('appointments_count')->label('Upcoming'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('slug')
                    ->label('Booking link')
                    ->formatStateUsing(fn (Calendar $record) => $record->bookingUrl())
                    ->copyable()
                    ->copyableState(fn (Calendar $record) => $record->bookingUrl())
                    ->color('primary'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Booking page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Calendar $record) => $record->bookingUrl(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
