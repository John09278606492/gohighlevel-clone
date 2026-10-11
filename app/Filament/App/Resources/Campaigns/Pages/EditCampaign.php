<?php

namespace App\Filament\App\Resources\Campaigns\Pages;

use App\Filament\App\Resources\Campaigns\CampaignResource;
use App\Filament\App\Resources\Campaigns\Pages\Concerns\SendsCampaign;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    use SendsCampaign;

    protected static string $resource = CampaignResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->record->isEditable()) {
            $this->redirect(CampaignResource::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normaliseBody($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->campaignActions(),
            Action::make('unschedule')
                ->label('Back to draft')
                ->color('gray')
                ->visible(fn () => $this->record->status === 'scheduled')
                ->action(fn () => $this->record->update(['status' => 'draft', 'scheduled_at' => null])),
            DeleteAction::make(),
        ];
    }
}
