<?php

namespace App\Filament\Agency\Resources\SubAccounts;

use App\Filament\Agency\Resources\SubAccounts\Pages\CreateSubAccount;
use App\Filament\Agency\Resources\SubAccounts\Pages\EditSubAccount;
use App\Filament\Agency\Resources\SubAccounts\Pages\ListSubAccounts;
use App\Filament\Agency\Resources\SubAccounts\Schemas\SubAccountForm;
use App\Filament\Agency\Resources\SubAccounts\Tables\SubAccountsTable;
use App\Models\SubAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SubAccountResource extends Resource
{
    protected static ?string $model = SubAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Sub-accounts';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SubAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Agency\Resources\SubAccounts\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubAccounts::route('/'),
            'create' => CreateSubAccount::route('/create'),
            'edit' => EditSubAccount::route('/{record}/edit'),
        ];
    }
}
