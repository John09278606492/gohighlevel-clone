<?php

namespace App\Filament\App\Resources\Funnels\RelationManagers;

use App\Filament\App\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** The steps of a funnel (or the pages of a website), in order. */
class PagesRelationManager extends RelationManager
{
    protected static string $relationship = 'pages';

    protected static ?string $title = 'Steps';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            TextInput::make('slug')->helperText('Leave blank to build it from the title.'),
            Toggle::make('is_published'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('step_order')
            ->defaultSort('step_order')
            ->columns([
                TextColumn::make('step_order')->label('#')->formatStateUsing(fn ($state, $rowLoop) => $rowLoop->iteration),
                TextColumn::make('title')->weight('medium')->description(fn (Page $record) => '/'.$record->slug),
                ToggleColumn::make('is_published')->label('Published'),
                TextColumn::make('views'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add step')
                    ->mutateDataUsing(fn (array $data) => $data + ['step_order' => ($this->getOwnerRecord()->pages()->max('step_order') ?? 0) + 1]),
            ])
            ->recordActions([
                Action::make('design')->label('Design')->icon('heroicon-o-paint-brush')
                    ->url(fn (Page $record) => PageResource::getUrl('design', ['record' => $record])),
                Action::make('preview')->label('Preview')->icon('heroicon-o-eye')->color('gray')
                    ->url(fn (Page $record) => $record->publicUrl(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
