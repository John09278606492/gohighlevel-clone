<?php

namespace App\Filament\App\Resources\CustomValues\Pages;

use App\Filament\App\Resources\CustomValues\CustomValueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomValues extends ManageRecords
{
    protected static string $resource = CustomValueResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
