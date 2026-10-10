<?php

namespace App\Filament\App\Resources\Funnels\Pages;

use App\Filament\App\Resources\Funnels\FunnelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFunnels extends ListRecords
{
    protected static string $resource = FunnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
