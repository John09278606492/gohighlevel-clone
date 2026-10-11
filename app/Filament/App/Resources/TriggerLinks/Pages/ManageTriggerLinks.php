<?php

namespace App\Filament\App\Resources\TriggerLinks\Pages;

use App\Filament\App\Resources\TriggerLinks\TriggerLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTriggerLinks extends ManageRecords
{
    protected static string $resource = TriggerLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
