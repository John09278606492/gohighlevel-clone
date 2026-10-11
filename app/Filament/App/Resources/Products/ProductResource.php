<?php

namespace App\Filament\App\Resources\Products;

use App\Filament\App\Resources\Products\Pages\ManageProducts;
use App\Models\Course;
use App\Models\Product;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(150)->columnSpanFull(),
            TextInput::make('price')->numeric()->minValue(0)->required()->prefix(fn () => Filament::getTenant()?->currency),
            Select::make('billing')->options(Product::BILLING)->default('one_time')->required(),
            Select::make('course_id')->label('Grants access to course')
                ->options(fn () => Course::where('sub_account_id', Access::tenantId())->pluck('title', 'id'))
                ->helperText('When an invoice with this product is paid, the contact gets the course.'),
            Toggle::make('is_active')->default(true)->inline(false),
            Textarea::make('description')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('medium')->searchable()->description(fn (Product $r) => $r->course?->title ? 'Unlocks '.$r->course->title : null),
                TextColumn::make('price')->money(fn () => Filament::getTenant()?->currency ?? 'USD')->sortable(),
                TextColumn::make('billing')->badge()->formatStateUsing(fn ($state) => Product::BILLING[$state] ?? $state),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
