<?php

namespace App\Filament\Agency\Resources\SubAccounts\Pages;

use App\Filament\Agency\Resources\SubAccounts\SubAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubAccount extends EditRecord
{
    protected static string $resource = SubAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
