<?php

namespace App\Filament\App\Resources\Pipelines\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PipelineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()->schema([
                    TextInput::make('name')->required()->maxLength(255),
                ]),
                Section::make('Stages')
                    ->description('Drag to reorder. Each stage becomes a column on the pipeline board.')
                    ->schema([
                        Repeater::make('stages')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->reorderable()
                            ->columns(2)
                            ->defaultItems(3)
                            ->minItems(1)
                            ->itemLabel(fn (array $state) => $state['name'] ?? null)
                            ->schema([
                                TextInput::make('name')->required(),
                                Select::make('color')
                                    ->options([
                                        'gray' => 'Gray',
                                        'info' => 'Blue',
                                        'primary' => 'Indigo',
                                        'warning' => 'Amber',
                                        'success' => 'Green',
                                        'danger' => 'Red',
                                    ])
                                    ->default('gray')
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
