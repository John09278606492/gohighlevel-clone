<?php

namespace App\Filament\App\Resources\Funnels;

use App\Filament\App\Resources\Funnels\Pages\CreateFunnel;
use App\Filament\App\Resources\Funnels\Pages\EditFunnel;
use App\Filament\App\Resources\Funnels\Pages\ListFunnels;
use App\Filament\App\Resources\Funnels\Schemas\FunnelForm;
use App\Filament\App\Resources\Funnels\Tables\FunnelsTable;
use App\Models\Funnel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FunnelResource extends Resource
{
    protected static ?string $model = Funnel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static string|\UnitEnum|null $navigationGroup = 'Sites';

    protected static ?string $navigationLabel = 'Funnels & websites';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FunnelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FunnelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\App\Resources\Funnels\RelationManagers\PagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFunnels::route('/'),
            'create' => CreateFunnel::route('/create'),
            'edit' => EditFunnel::route('/{record}/edit'),
        ];
    }
}
