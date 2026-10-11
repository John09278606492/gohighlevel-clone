<?php

namespace App\Filament\App\Resources\TriggerLinks;

use App\Filament\App\Resources\TriggerLinks\Pages\ManageTriggerLinks;
use App\Models\TriggerLink;
use App\Support\TenantTags;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TriggerLinkResource extends Resource
{
    protected static ?string $model = TriggerLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100)->placeholder('Spring offer'),
            TextInput::make('url')->label('Destination URL')->url()->required()->maxLength(2048),
            TagsInput::make('add_tags')->label('Tag contacts who click')->suggestions(fn () => array_values(TenantTags::options())),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->weight('medium')->searchable()->description(fn (TriggerLink $r) => $r->url),
                TextColumn::make('code')->label('Merge tag')->fontFamily('mono')->copyable()
                    ->formatStateUsing(fn ($state) => "{{ link.{$state} }}")
                    ->copyableState(fn ($state) => "{{ link.{$state} }}"),
                TextColumn::make('short')->label('Link')->state(fn (TriggerLink $r) => $r->shortUrl())->copyable()->limit(40),
                TextColumn::make('clicks_count')->label('Clicks')->numeric()->sortable(),
                TextColumn::make('unique_contacts_count')->label('Contacts')->numeric(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateDescription('Put {{ link.code }} in an email or SMS: you see who clicked, they get tagged, and "Trigger link clicked" workflows run.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageTriggerLinks::route('/')];
    }
}
