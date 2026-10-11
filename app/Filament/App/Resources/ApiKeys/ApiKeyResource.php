<?php

namespace App\Filament\App\Resources\ApiKeys;

use App\Filament\App\Resources\ApiKeys\Pages\ManageApiKeys;
use App\Models\ApiKey;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'API keys';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return Access::isTenantAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('medium'),
                TextColumn::make('prefix')->label('Key')->fontFamily('mono')->formatStateUsing(fn ($state) => $state.'…'),
                TextColumn::make('last_used_at')->since()->placeholder('Never'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->recordActions([DeleteAction::make()->label('Revoke')->modalHeading('Revoke API key')])
            ->emptyStateDescription('Create a key to connect Zapier, Make or your own code to this sub-account.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageApiKeys::route('/')];
    }
}
