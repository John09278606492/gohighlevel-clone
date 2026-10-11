<?php

namespace App\Filament\App\Resources\Campaigns\Pages;

use App\Filament\App\Resources\Campaigns\CampaignResource;
use App\Filament\App\Resources\Campaigns\Pages\Concerns\SendsCampaign;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    use SendsCampaign;

    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normaliseBody($data) + ['user_id' => auth()->id(), 'status' => 'draft'];
    }

    protected function getRedirectUrl(): string
    {
        return CampaignResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draft saved. Send a test, schedule it or send it now.';
    }
}
