<?php

namespace App\Filament\App\Resources\Contacts\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->columnSpanFull(),
            DateTimePicker::make('due_at'),
            Select::make('assigned_to')
                ->label('Assigned to')
                ->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? []),
            Textarea::make('description')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_at')
            ->columns([
                CheckboxColumn::make('is_completed')->label('Done'),
                TextColumn::make('title')->wrap(),
                TextColumn::make('due_at')->dateTime()->color(fn ($record) => ! $record->is_completed && $record->due_at?->isPast() ? 'danger' : null),
                TextColumn::make('assignee.name')->label('Assigned to'),
            ])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['sub_account_id' => Filament::getTenant()->getKey()]),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
