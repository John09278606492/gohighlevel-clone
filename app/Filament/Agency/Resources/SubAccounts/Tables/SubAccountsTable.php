<?php

namespace App\Filament\Agency\Resources\SubAccounts\Tables;

use App\Models\SubAccount;
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
                ->withSum(['opportunities' => fn ($q) => $q->where('status', 'open')], 'value'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium')->description(fn (SubAccount $record) => $record->email),
                TextColumn::make('contacts_count')->label('Contacts')->sortable(),
                TextColumn::make('opportunities_sum_value')->label('Open pipeline')->money('USD')->sortable(),
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
                EditAction::make(),
            ]);
    }
}
