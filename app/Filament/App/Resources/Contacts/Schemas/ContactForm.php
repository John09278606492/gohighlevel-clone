<?php

namespace App\Filament\App\Resources\Contacts\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Contact')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')->maxLength(255),
                        TextInput::make('last_name')->maxLength(255),
                        TextInput::make('email')->email()->maxLength(255)->requiredWithout('phone'),
                        TextInput::make('phone')->tel()->maxLength(50)->requiredWithout('email'),
                        TextInput::make('company')->maxLength(255),
                        TextInput::make('source')->placeholder('Website, Facebook ad, referral…'),
                        SpatieTagsInput::make('tags')->columnSpanFull(),
                    ]),
                Section::make('Status')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('type')
                            ->options(['lead' => 'Lead', 'customer' => 'Customer'])
                            ->default('lead')
                            ->required(),
                        Select::make('owner_id')
                            ->label('Assigned to')
                            ->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? [])
                            ->searchable(),
                        Toggle::make('dnd')
                            ->label('Do not disturb')
                            ->helperText('Workflows will not message this contact.'),
                    ]),
                Section::make('Details')
                    ->columnSpan(2)
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        DatePicker::make('date_of_birth'),
                        TextInput::make('address'),
                        TextInput::make('city'),
                        TextInput::make('country'),
                        KeyValue::make('custom_fields')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
