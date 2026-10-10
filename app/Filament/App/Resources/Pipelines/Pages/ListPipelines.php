<?php

namespace App\Filament\App\Resources\Pipelines\Pages;

use App\Filament\App\Resources\Pipelines\PipelineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPipelines extends ListRecords
{
    protected static string $resource = PipelineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
