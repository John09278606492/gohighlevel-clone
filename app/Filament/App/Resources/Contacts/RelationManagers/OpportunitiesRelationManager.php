<?php

namespace App\Filament\App\Resources\Contacts\RelationManagers;

use App\Filament\App\Resources\Opportunities\OpportunityResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OpportunitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'opportunities';

    public function form(Schema $schema): Schema
    {
        return OpportunityResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return OpportunityResource::table($table)->modifyQueryUsing(fn ($query) => $query);
    }
}
