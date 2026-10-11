<?php

namespace App\Filament\Agency\Resources\SubAccounts\Tables;

use App\Models\Snapshot;
use App\Models\SubAccount;
use App\Services\SnapshotService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount(['contacts', 'users', 'appointments' => fn ($q) => $q->where('starts_at', '>=', now())])
                ->withSum(['opportunities' => fn ($q) => $q->where('status', 'open')], 'value')
                ->withSum(['payments' => fn ($q) => $q->where('paid_at', '>=', now()->subDays(30))], 'amount')
                ->withAvg('reviews', 'rating'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium')->description(fn (SubAccount $record) => $record->email),
                TextColumn::make('contacts_count')->label('Contacts')->sortable(),
                TextColumn::make('opportunities_sum_value')->label('Open pipeline')->money('USD')->sortable(),
                TextColumn::make('payments_sum_amount')->label('Revenue (30d)')->money('USD')->sortable()->placeholder('-'),
                TextColumn::make('reviews_avg_rating')->label('Rating')->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 1).' ★' : '-'),
                TextColumn::make('appointments_count')->label('Upcoming appts'),
                TextColumn::make('users_count')->label('Users'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('created_at')->since()->label('Created')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Switch to')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(fn (SubAccount $record) => url('/app/'.$record->slug)),
                Action::make('snapshot')
                    ->label('Load snapshot')
                    ->icon('heroicon-o-arrow-down-on-square-stack')
                    ->color('gray')
                    ->schema([Select::make('snapshot_id')->label('Snapshot')->options(fn () => Snapshot::latest()->pluck('name', 'id'))->required()])
                    ->action(function (SubAccount $record, array $data): void {
                        $created = SnapshotService::apply(Snapshot::findOrFail($data['snapshot_id']), $record);
                        Notification::make()->success()->title('Snapshot loaded into '.$record->name)
                            ->body(collect($created)->map(fn ($n, $k) => "{$n} ".str_replace('_', ' ', $k))->implode(', ') ?: 'Nothing new to add.')->send();
                    }),
                EditAction::make(),
            ]);
    }
}
