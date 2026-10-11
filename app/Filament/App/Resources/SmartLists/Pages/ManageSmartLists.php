<?php

namespace App\Filament\App\Resources\SmartLists\Pages;

use App\Filament\App\Resources\SmartLists\SmartListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSmartLists extends ManageRecords
{
    protected static string $resource = SmartListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['user_id' => auth()->id()]),
        ];
    }
}
