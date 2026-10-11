<?php

namespace App\Filament\App\Resources\Contacts\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use App\Models\CustomField;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactForm
{
    /** One input per custom field of the sub-account, stored in custom_fields.<key>. */
    public static function customFieldComponents(): array
    {
        return CustomField::where('sub_account_id', Filament::getTenant()?->getKey())
            ->orderBy('sort')
            ->get()
            ->map(function (CustomField $field) {
                $name = "custom_fields.{$field->key}";
                $component = match ($field->type) {
                    'textarea' => Textarea::make($name)->rows(3)->columnSpanFull(),
                    'number' => TextInput::make($name)->numeric(),
                    'date' => DatePicker::make($name),
                    'select' => Select::make($name)->options(array_combine($field->options ?? [], $field->options ?? [])),
                    'checkbox' => Toggle::make($name)->inline(false),
                    'url' => TextInput::make($name)->url(),
                    default => TextInput::make($name),
                };

                return $component
                    ->label($field->name)
                    ->placeholder($field->type === 'checkbox' ? null : $field->placeholder)
                    ->required($field->is_required);
            })
            ->all();
    }

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
                            ->helperText('Workflows and campaigns will not message this contact.'),
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
                    ]),
                Section::make('Custom fields')
                    ->columnSpan(2)
                    ->columns(2)
                    ->visible(fn () => CustomField::where('sub_account_id', Filament::getTenant()?->getKey())->exists())
                    ->schema(fn () => static::customFieldComponents()),
                Section::make('Communication')
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('dnd_email')->label('Unsubscribed from email'),
                        Toggle::make('dnd_sms')->label('Opted out of SMS'),
                    ]),
            ]);
    }
}
