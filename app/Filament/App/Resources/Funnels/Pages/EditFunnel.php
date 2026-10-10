<?php

namespace App\Filament\App\Resources\Funnels\Pages;

use App\Filament\App\Resources\Funnels\FunnelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFunnel extends EditRecord
{
    protected static string $resource = FunnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
