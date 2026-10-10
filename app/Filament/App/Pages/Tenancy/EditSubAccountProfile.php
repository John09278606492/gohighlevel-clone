<?php

namespace App\Filament\App\Pages\Tenancy;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditSubAccountProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Business profile';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Business info')->columns(2)->schema([
                TextInput::make('name')->label('Business name')->required(),
                TextInput::make('email')->email(),
                TextInput::make('phone')->tel(),
                TextInput::make('website')->url(),
                TextInput::make('address')->columnSpanFull(),
                Select::make('timezone')->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))->searchable(),
                FileUpload::make('logo')->image()->disk('public')->directory('logos'),
            ]),
        ]);
    }
}
