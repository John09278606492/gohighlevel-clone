<?php

namespace App\Filament\App\Resources\Documents\Pages;

use App\Filament\App\Resources\Documents\DocumentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DocumentResource::sendAction()->record($this->record),
            Action::make('preview')->label('Preview')->icon('heroicon-o-eye')->color('gray')->url(fn () => $this->record->publicUrl(), true),
            DeleteAction::make(),
        ];
    }
}
