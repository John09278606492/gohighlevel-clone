<?php

namespace App\Filament\App\Resources\CustomValues;

use App\Filament\App\Resources\CustomValues\Pages\ManageCustomValues;
use App\Models\CustomValue;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Sub-account wide merge values ({{ custom_values.key }}) such as the office address or a promo code. */
class CustomValueResource extends Resource
{
    protected static ?string $model = CustomValue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVariable;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return Access::isTenantAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->required()->alphaDash()->maxLength(60)
                ->helperText('Use it in messages and pages as {{ custom_values.key }}.')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('sub_account_id', Access::tenantId())),
            Textarea::make('value')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->fontFamily('mono')->searchable()->formatStateUsing(fn ($state) => "{{ custom_values.{$state} }}")->copyable(),
                TextColumn::make('value')->limit(80)->wrap(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomValues::route('/')];
    }
}
