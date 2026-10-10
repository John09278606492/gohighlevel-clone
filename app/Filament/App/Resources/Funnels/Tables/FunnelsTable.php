<?php

namespace App\Filament\App\Resources\Funnels\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FunnelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('pages')->withSum('pages', 'views'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium'),
                TextColumn::make('type')->badge(),
                TextColumn::make('pages_count')->label('Steps'),
                TextColumn::make('pages_sum_views')->label('Views')->default(0),
                TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->recordActions([EditAction::make()->label('Open'), DeleteAction::make()]);
    }
}
