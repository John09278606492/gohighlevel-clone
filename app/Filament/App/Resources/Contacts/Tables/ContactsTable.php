<?php

namespace App\Filament\App\Resources\Contacts\Tables;

use App\Models\Contact;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TagsInput;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Tags\Tag;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->weight('medium')
                    ->description(fn (Contact $record) => $record->company)
                    ->searchable(['first_name', 'last_name', 'company'])
                    ->sortable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->icon('heroicon-m-envelope')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->icon('heroicon-m-phone')
                    ->searchable()
                    ->toggleable(),
                SpatieTagsColumn::make('tags'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'customer' ? 'success' : 'info'),
                TextColumn::make('source')
                    ->toggleable(),
                TextColumn::make('owner.name')
                    ->label('Assigned to')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_activity_at')
                    ->label('Last activity')
                    ->since()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(['lead' => 'Lead', 'customer' => 'Customer']),
                SelectFilter::make('tag')
                    ->label('Tag')
                    ->multiple()
                    ->options(fn () => Tag::query()->orderBy('name->en')->get()->pluck('name', 'name')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['values'] ?? null)
                        ? $query->withAnyTags($data['values'])
                        : $query),
                SelectFilter::make('owner_id')
                    ->label('Assigned to')
                    ->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? []),
                TernaryFilter::make('dnd')
                    ->label('Do not disturb'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('addTags')
                        ->label('Add tags')
                        ->icon('heroicon-o-tag')
                        ->schema([TagsInput::make('tags')->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->attachTags($data['tags']))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('removeTags')
                        ->label('Remove tags')
                        ->icon('heroicon-o-x-mark')
                        ->schema([TagsInput::make('tags')->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->detachTags($data['tags']))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
