<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Models\SubAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;

class RegisterSubAccount extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Create sub-account';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Business name')->required()->maxLength(255),
            TextInput::make('email')->email(),
            TextInput::make('phone')->tel(),
            Select::make('timezone')->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))->searchable()->default(config('app.timezone')),
        ]);
    }

    protected function handleRegistration(array $data): SubAccount
    {
        $account = SubAccount::create($data);
        $account->users()->attach(auth()->user(), ['role' => 'admin']);

        return $account;
    }
}
