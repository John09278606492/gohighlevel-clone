<?php

namespace App\Filament\App\Resources\CustomFields;

use App\Filament\App\Resources\CustomFields\Pages\ManageCustomFields;
use App\Models\CustomField;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class CustomFieldResource extends Resource
{
    protected static ?string $model = CustomField::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return Access::isTenantAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(100)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set, Get $get) => blank($get('key')) ? $set('key', str($state)->snake()->toString()) : null),
            TextInput::make('key')
                ->required()
                ->alphaDash()
                ->maxLength(60)
                ->helperText('Use it in messages as {{ contact.custom.key }}.')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('sub_account_id', Access::tenantId())),
            Select::make('type')->options(CustomField::TYPES)->default('text')->required()->live(),
            TextInput::make('placeholder')->maxLength(150),
            TagsInput::make('options')
                ->helperText('Press Enter after each option.')
                ->visible(fn (Get $get) => $get('type') === 'select')
                ->required(fn (Get $get) => $get('type') === 'select')
                ->columnSpanFull(),
            Toggle::make('is_required')->label('Required'),
            TextInput::make('sort')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium'),
                TextColumn::make('key')->fontFamily('mono')->copyable()->formatStateUsing(fn ($state) => "{{ contact.custom.{$state} }}"),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => CustomField::TYPES[$state] ?? $state),
                IconColumn::make('is_required')->label('Required')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateDescription('Add fields like "Insurance provider" or "Roof age" to every contact in this sub-account.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomFields::route('/')];
    }
}
