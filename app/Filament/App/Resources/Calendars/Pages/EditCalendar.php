<?php

namespace App\Filament\App\Resources\Calendars\Pages;

use App\Filament\App\Resources\Calendars\CalendarResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCalendar extends EditRecord
{
    protected static string $resource = CalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
