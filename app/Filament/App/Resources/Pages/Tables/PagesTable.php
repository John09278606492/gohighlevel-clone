<?php

namespace App\Filament\App\Resources\Pages\Tables;

use App\Filament\App\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->weight('medium')->description(fn (Page $record) => '/'.$record->slug),
                TextColumn::make('funnel.name')->label('Funnel')->placeholder('-'),
                ToggleColumn::make('is_published')->label('Published'),
                TextColumn::make('views')->sortable(),
                TextColumn::make('updated_at')->since()->label('Updated'),
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
