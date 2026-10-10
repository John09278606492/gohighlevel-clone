<?php

namespace App\Filament\App\Resources\Contacts\RelationManagers;

use App\Filament\App\Resources\Appointments\AppointmentResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    public function form(Schema $schema): Schema
    {
        return AppointmentResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return AppointmentResource::table($table);
    }
}
