<?php

namespace App\Filament\App\Resources\Opportunities\Pages;

use App\Filament\App\Resources\Opportunities\OpportunityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOpportunities extends ManageRecords
{
    protected static string $resource = OpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
