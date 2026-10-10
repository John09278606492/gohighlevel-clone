<?php

namespace App\Filament\App\Resources\Pipelines\Tables;

use App\Filament\App\Pages\PipelineBoard;
use App\Models\Pipeline;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PipelinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['stages', 'opportunities'])->withSum(['opportunities' => fn ($q) => $q->where('status', 'open')], 'value'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium'),
                TextColumn::make('stages_count')->label('Stages'),
                TextColumn::make('opportunities_count')->label('Opportunities'),
                TextColumn::make('opportunities_sum_value')->label('Open value')->money('USD'),
            ])
            ->recordActions([
                Action::make('board')
                    ->label('Open board')
                    ->icon('heroicon-o-view-columns')
                    ->url(fn (Pipeline $record) => PipelineBoard::getUrl(['pipeline' => $record->id])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
