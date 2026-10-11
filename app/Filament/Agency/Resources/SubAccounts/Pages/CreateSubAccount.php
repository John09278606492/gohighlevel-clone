<?php

namespace App\Filament\Agency\Resources\SubAccounts\Pages;

use App\Filament\Agency\Resources\SubAccounts\SubAccountResource;
use App\Models\Snapshot;
use App\Services\SnapshotService;
use Filament\Resources\Pages\CreateRecord;

class CreateSubAccount extends CreateRecord
{
    protected static string $resource = SubAccountResource::class;

    protected function afterCreate(): void
    {
        if ($snapshot = Snapshot::find($this->data['snapshot_id'] ?? null)) {
            SnapshotService::apply($snapshot, $this->record);
        }
    }
}
