<?php

namespace App\Filament\Agency\Resources\SubAccounts\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Business')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Business name')->required(),
                TextInput::make('slug')->helperText('Used in the app URL and public links. Leave blank to generate.')->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('email')->email(),
                TextInput::make('phone')->tel(),
                TextInput::make('website')->url(),
                Select::make('timezone')->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))->searchable()->default(config('app.timezone')),
                TextInput::make('address')->columnSpanFull(),
                FileUpload::make('logo')->image()->disk('public')->directory('logos'),
                Toggle::make('is_active')->default(true),
            ]),
        ]);
    }
}
