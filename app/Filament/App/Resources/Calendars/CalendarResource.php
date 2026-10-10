<?php

namespace App\Filament\App\Resources\Calendars;

use App\Filament\App\Resources\Calendars\Pages\CreateCalendar;
use App\Filament\App\Resources\Calendars\Pages\EditCalendar;
use App\Filament\App\Resources\Calendars\Pages\ListCalendars;
use App\Filament\App\Resources\Calendars\Schemas\CalendarForm;
use App\Filament\App\Resources\Calendars\Tables\CalendarsTable;
use App\Models\Calendar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CalendarResource extends Resource
{
    protected static ?string $model = Calendar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Calendars';

    protected static ?string $navigationLabel = 'Calendar settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CalendarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalendarsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalendars::route('/'),
            'create' => CreateCalendar::route('/create'),
            'edit' => EditCalendar::route('/{record}/edit'),
        ];
    }
}
