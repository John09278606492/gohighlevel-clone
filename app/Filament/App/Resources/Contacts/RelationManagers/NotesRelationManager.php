<?php

namespace App\Filament\App\Resources\Contacts\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notes & activity';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')->label('Note')->rows(5)->required()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')->label('Note')->wrap()->formatStateUsing(fn (string $state) => nl2br(e($state)))->html(),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('created_at')->since()->label('When'),
            ])
            ->headerActions([CreateAction::make()->label('Add note')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
