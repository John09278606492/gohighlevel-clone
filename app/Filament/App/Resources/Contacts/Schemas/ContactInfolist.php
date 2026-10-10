<?php

namespace App\Filament\App\Resources\Contacts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\SpatieTagsEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactInfolist
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
                        TextEntry::make('full_name')->label('Name')->weight('bold'),
                        TextEntry::make('company')->placeholder('-'),
                        TextEntry::make('email')->icon('heroicon-m-envelope')->copyable()->placeholder('-'),
                        TextEntry::make('phone')->icon('heroicon-m-phone')->copyable()->placeholder('-'),
                        SpatieTagsEntry::make('tags')->columnSpanFull()->placeholder('No tags'),
                        TextEntry::make('address')->placeholder('-'),
                        TextEntry::make('city')->placeholder('-'),
                        KeyValueEntry::make('custom_fields')->columnSpanFull()->visible(fn ($record) => filled($record->custom_fields)),
                    ]),
                Section::make('Status')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('type')->badge()->color(fn (string $state) => $state === 'customer' ? 'success' : 'info'),
                        TextEntry::make('source')->placeholder('-'),
                        TextEntry::make('owner.name')->label('Assigned to')->placeholder('Unassigned'),
                        IconEntry::make('dnd')->label('Do not disturb')->boolean(),
                        TextEntry::make('last_activity_at')->since()->placeholder('-'),
                        TextEntry::make('created_at')->label('Added')->dateTime(),
                    ]),
            ]);
    }
}
