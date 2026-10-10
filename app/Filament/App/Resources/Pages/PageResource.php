<?php

namespace App\Filament\App\Resources\Pages;

use App\Filament\App\Resources\Pages\Pages\CreatePage;
use App\Filament\App\Resources\Pages\Pages\DesignPage;
use App\Filament\App\Resources\Pages\Pages\EditPage;
use App\Filament\App\Resources\Pages\Pages\ListPages;
use App\Filament\App\Resources\Pages\Schemas\PageForm;
use App\Filament\App\Resources\Pages\Tables\PagesTable;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Sites';

    protected static ?string $navigationLabel = 'Pages';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
            'design' => DesignPage::route('/{record}/design'),
        ];
    }
}
