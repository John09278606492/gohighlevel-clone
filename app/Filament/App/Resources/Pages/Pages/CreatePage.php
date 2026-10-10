<?php

namespace App\Filament\App\Resources\Pages\Pages;

use App\Filament\App\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function getRedirectUrl(): string
    {
        return PageResource::getUrl('design', ['record' => $this->record]);
    }
}
