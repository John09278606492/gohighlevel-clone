<?php

namespace App\Filament\App\Resources\Calendars\Schemas;

use App\Models\Calendar;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CalendarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Calendar')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                        TextInput::make('slug')
                            ->label('Booking link')
                            ->prefix('/book/'.Filament::getTenant()?->slug.'/')
                            ->required()
                            ->alphaDash(),
                        Textarea::make('description')->columnSpanFull(),
                        Select::make('user_id')
                            ->label('Team member')
                            ->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? []),
                        ColorPicker::make('color')->default('#6366f1'),
                        TextInput::make('confirmation_message')
                            ->placeholder('Thanks! You are booked in.')
                            ->columnSpanFull(),
                        Toggle::make('is_active')->label('Accepting bookings')->default(true),
                    ]),
                Section::make('Meeting')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('duration_minutes')->label('Duration')->numeric()->suffix('min')->default(30)->required()->minValue(5),
                        TextInput::make('slot_interval_minutes')->label('Slot interval')->numeric()->suffix('min')->default(30)->required()->minValue(5),
                        TextInput::make('minimum_notice_minutes')->label('Minimum notice')->numeric()->suffix('min')->default(60),
                        TextInput::make('booking_window_days')->label('Bookable up to')->numeric()->suffix('days ahead')->default(60),
                        Select::make('timezone')
                            ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                            ->searchable()
                            ->default(fn () => Filament::getTenant()?->timezone ?? 'UTC')
                            ->required(),
                    ]),
                Section::make('Availability')
                    ->columnSpan(2)
                    ->schema([
                        Repeater::make('working_hours')
                            ->hiddenLabel()
                            ->default(Calendar::defaultWorkingHours())
                            ->columns(3)
                            ->addActionLabel('Add day')
                            ->schema([
                                Select::make('day')
                                    ->options(array_combine(Calendar::WEEKDAYS, array_map('ucfirst', Calendar::WEEKDAYS)))
                                    ->required()
                                    ->distinct(),
                                TimePicker::make('start')->seconds(false)->format('H:i')->required(),
                                TimePicker::make('end')->seconds(false)->format('H:i')->required()->after('start'),
                            ]),
                    ]),
            ]);
    }
}
