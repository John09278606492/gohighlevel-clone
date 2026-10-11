<?php

namespace App\Filament\Agency\Resources\Snapshots\Pages;

use App\Filament\Agency\Resources\Snapshots\SnapshotResource;
use App\Models\Snapshot;
use App\Models\SubAccount;
use App\Services\SnapshotService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Storage;

class ManageSnapshots extends ManageRecords
{
    protected static string $resource = SnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('capture')
                ->label('Capture snapshot')
                ->icon('heroicon-o-camera')
                ->schema([
                    Select::make('sub_account_id')->label('From sub-account')->options(fn () => SubAccount::orderBy('name')->pluck('name', 'id'))->required(),
                    TextInput::make('name')->required()->maxLength(120)->placeholder('Dental practice starter'),
                    Textarea::make('description')->rows(2),
                    CheckboxList::make('assets')->options(SnapshotService::ASSETS)->default(array_keys(SnapshotService::ASSETS))->columns(2)->required(),
                ])
                ->action(function (array $data): void {
                    $snapshot = SnapshotService::capture(SubAccount::findOrFail($data['sub_account_id']), $data['name'], $data['assets'], $data['description'] ?? null);
                    Notification::make()->success()->title("Snapshot \"{$snapshot->name}\" captured")->send();
                }),
            Action::make('import')
                ->label('Import file')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->schema([FileUpload::make('file')->disk('local')->directory('imports')->acceptedFileTypes(['application/json', 'text/plain'])->required()])
                ->action(function (array $data): void {
                    $json = json_decode(Storage::disk('local')->get($data['file']), true);
                    Storage::disk('local')->delete($data['file']);

                    if (! is_array($json) || ! isset($json['payload']) || ! is_array($json['payload'])) {
                        Notification::make()->danger()->title('That is not a snapshot file')->send();

                        return;
                    }

                    Snapshot::create([
                        'name' => (string) ($json['name'] ?? 'Imported snapshot'),
                        'description' => $json['description'] ?? null,
                        'contents' => array_values(array_intersect(array_keys(SnapshotService::ASSETS), array_keys($json['payload']))),
                        'payload' => $json['payload'],
                    ]);
                    Notification::make()->success()->title('Snapshot imported')->send();
                }),
        ];
    }
}
