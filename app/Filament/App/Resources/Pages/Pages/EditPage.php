<?php

namespace App\Filament\App\Resources\Pages\Pages;

use App\Filament\App\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('design')->label('Open designer')->icon('heroicon-o-paint-brush')
                ->url(fn () => PageResource::getUrl('design', ['record' => $this->record])),
            Action::make('preview')->label('Preview')->icon('heroicon-o-eye')->color('gray')
                ->url(fn () => $this->record->publicUrl(), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
