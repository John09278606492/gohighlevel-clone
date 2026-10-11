<?php

namespace App\Filament\App\Resources\Contacts\Schemas;

use Filament\Infolists\Components\IconEntry;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Payment;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\SpatieTagsEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactInfolist
{
    protected static function customFieldEntries(): array
    {
        return CustomField::where('sub_account_id', Filament::getTenant()?->getKey())
            ->orderBy('sort')
            ->get()
            ->map(fn (CustomField $field) => TextEntry::make("custom_fields.{$field->key}")
                ->label($field->name)
                ->placeholder('-')
                ->formatStateUsing(fn ($state) => $field->type === 'checkbox' ? ($state ? 'Yes' : 'No') : $state))
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
                        TextEntry::make('full_name')->label('Name')->weight('bold'),
                        TextEntry::make('company')->placeholder('-'),
                        TextEntry::make('email')->icon('heroicon-m-envelope')->copyable()->placeholder('-'),
                        TextEntry::make('phone')->icon('heroicon-m-phone')->copyable()->placeholder('-'),
                        SpatieTagsEntry::make('tags')->columnSpanFull()->placeholder('No tags'),
                        TextEntry::make('address')->placeholder('-'),
                        TextEntry::make('city')->placeholder('-'),
                        ...static::customFieldEntries(),
                    ]),
                Section::make('Status')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('type')->badge()->color(fn (string $state) => $state === 'customer' ? 'success' : 'info'),
                        TextEntry::make('source')->placeholder('-'),
                        TextEntry::make('owner.name')->label('Assigned to')->placeholder('Unassigned'),
                        IconEntry::make('dnd')->label('Do not disturb')->boolean(),
                        TextEntry::make('subscriptions')
                            ->label('Opt-outs')
                            ->state(fn (Contact $record) => collect(['Email' => $record->dnd_email, 'SMS' => $record->dnd_sms])->filter()->keys()->implode(', ') ?: 'None'),
                        TextEntry::make('lifetime_value')
                            ->label('Lifetime value')
                            ->state(fn (Contact $record) => Payment::where('contact_id', $record->id)->sum('amount'))
                            ->money(fn (Contact $record) => $record->subAccount->currency ?? 'USD'),
                        TextEntry::make('last_activity_at')->since()->placeholder('-'),
                        TextEntry::make('created_at')->label('Added')->dateTime(),
                    ]),
                Section::make('Activity')
                    ->columnSpan(2)
                    ->collapsible()
                    ->schema([
                        ViewEntry::make('timeline')->hiddenLabel()->view('filament.app.contacts.timeline'),
                    ]),
            ]);
    }
}
