<?php

namespace App\Filament\App\Resources\Funnels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FunnelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required(),
                Select::make('type')->options(['funnel' => 'Funnel', 'website' => 'Website'])->default('funnel')->required(),
            ]),
        ]);
    }
}
