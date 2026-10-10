<?php

namespace App\Filament\App\Resources\Pages\Schemas;

use App\Models\Funnel;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Page')->columnSpan(2)->columns(2)->schema([
                    TextInput::make('title')->required(),
                    TextInput::make('slug')
                        ->prefix('/p/'.Filament::getTenant()?->slug.'/')
                        ->helperText('Leave blank to build it from the title.'),
                    Select::make('funnel_id')
                        ->label('Funnel / website')
                        ->options(fn () => Funnel::where('sub_account_id', Filament::getTenant()?->getKey())->pluck('name', 'id')),
                    TextInput::make('step_order')->numeric()->default(1),
                ]),
                Section::make('Publishing')->columnSpan(1)->schema([
                    Toggle::make('is_published'),
                    TextInput::make('seo_title'),
                    Textarea::make('seo_description'),
                ]),
            ]);
    }
}
