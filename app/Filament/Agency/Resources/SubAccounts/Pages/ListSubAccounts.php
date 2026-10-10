<?php

namespace App\Filament\Agency\Resources\SubAccounts\Pages;

use App\Filament\Agency\Resources\SubAccounts\SubAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubAccounts extends ListRecords
{
    protected static string $resource = SubAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
