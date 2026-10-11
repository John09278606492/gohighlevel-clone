<?php

namespace App\Filament\Agency\Resources\Snapshots;

use App\Filament\Agency\Resources\Snapshots\Pages\ManageSnapshots;
use App\Models\Snapshot;
use App\Models\SubAccount;
use App\Services\SnapshotService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Reusable sub-account setups (GoHighLevel "snapshots"). */
class SnapshotResource extends Resource
{
    protected static ?string $model = Snapshot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->weight('medium')->searchable()->description(fn (Snapshot $r) => $r->description),
                TextColumn::make('source.name')->label('Captured from')->placeholder('Imported'),
                TextColumn::make('contents')->badge()->formatStateUsing(fn ($state) => Str::before(SnapshotService::ASSETS[$state] ?? $state, ' and')),
                TextColumn::make('created_at')->since(),
            ])
            ->recordActions([
                Action::make('load')
                    ->label('Load into…')
                    ->icon('heroicon-o-arrow-down-on-square-stack')
                    ->modalDescription('Adds this snapshot\'s assets to each sub-account. Nothing is deleted; workflows arrive switched off so you can review them.')
                    ->schema([
                        Select::make('sub_accounts')->label('Sub-accounts')->multiple()->required()
                            ->options(fn () => SubAccount::orderBy('name')->pluck('name', 'id')),
                    ])
                    ->action(function (Snapshot $record, array $data): void {
                        $summary = [];
                        foreach (SubAccount::whereIn('id', $data['sub_accounts'])->get() as $account) {
                            $created = SnapshotService::apply($record, $account);
                            $summary[] = $account->name.': '.(collect($created)->map(fn ($n, $k) => "{$n} ".str_replace('_', ' ', $k))->implode(', ') ?: 'nothing new');
                        }
                        Notification::make()->success()->title('Snapshot loaded')->body(implode("\n", $summary))->persistent()->send();
                    }),
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (Snapshot $record) => response()->streamDownload(
                        fn () => print(json_encode(['name' => $record->name, 'description' => $record->description, 'contents' => $record->contents, 'payload' => $record->payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                        Str::slug($record->name).'.snapshot.json',
                        ['Content-Type' => 'application/json'],
                    )),
                DeleteAction::make(),
            ])
            ->emptyStateDescription('Capture a sub-account you\'ve set up well, then load it into new clients in one click.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageSnapshots::route('/')];
    }
}
