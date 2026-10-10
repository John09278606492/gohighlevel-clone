<?php

namespace App\Filament\Agency\Resources\SubAccounts\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Team members';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')->options(['admin' => 'Admin', 'user' => 'User'])->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('role')->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        Select::make('role')->options(['admin' => 'Admin', 'user' => 'User'])->default('user')->required(),
                    ]),
            ])
            ->recordActions([EditAction::make(), DetachAction::make()]);
    }
}
