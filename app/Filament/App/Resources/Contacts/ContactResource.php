<?php

namespace App\Filament\App\Resources\Contacts;

use App\Filament\App\Resources\Contacts\Pages\CreateContact;
use App\Filament\App\Resources\Contacts\Pages\EditContact;
use App\Filament\App\Resources\Contacts\Pages\ListContacts;
use App\Filament\App\Resources\Contacts\Pages\ViewContact;
use App\Filament\App\Resources\Contacts\RelationManagers\AppointmentsRelationManager;
use App\Filament\App\Resources\Contacts\RelationManagers\NotesRelationManager;
use App\Filament\App\Resources\Contacts\RelationManagers\OpportunitiesRelationManager;
use App\Filament\App\Resources\Contacts\RelationManagers\TasksRelationManager;
use App\Filament\App\Resources\Contacts\Schemas\ContactForm;
use App\Filament\App\Resources\Contacts\Schemas\ContactInfolist;
use App\Filament\App\Resources\Contacts\Tables\ContactsTable;
use App\Models\Contact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return ContactForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContactInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactsTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email', 'phone', 'company'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter(['Email' => $record->email, 'Phone' => $record->phone]);
    }

    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
            OpportunitiesRelationManager::class,
            AppointmentsRelationManager::class,
            TasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'view' => ViewContact::route('/{record}'),
            'edit' => EditContact::route('/{record}/edit'),
        ];
    }
}
