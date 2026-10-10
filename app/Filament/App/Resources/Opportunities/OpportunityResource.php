<?php

namespace App\Filament\App\Resources\Opportunities;

use App\Filament\App\Resources\Opportunities\Pages\ManageOpportunities;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Opportunities list';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Opportunity name')
                    ->required()
                    ->columnSpanFull(),
                Select::make('contact_id')
                    ->label('Contact')
                    ->relationship('contact', 'email', modifyQueryUsing: fn ($query) => $query->where('sub_account_id', Filament::getTenant()?->getKey()))
                    ->getOptionLabelFromRecordUsing(fn (Contact $record) => $record->full_name.($record->email ? " ({$record->email})" : ''))
                    ->searchable(['first_name', 'last_name', 'email', 'phone'])
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (blank($get('name')) && $state) {
                            $set('name', Contact::find($state)?->full_name);
                        }
                    })
                    ->createOptionForm([
                        TextInput::make('first_name'),
                        TextInput::make('last_name'),
                        TextInput::make('email')->email(),
                        TextInput::make('phone')->tel(),
                    ]),
                TextInput::make('value')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),
                Select::make('pipeline_id')
                    ->label('Pipeline')
                    ->options(fn () => Pipeline::where('sub_account_id', Filament::getTenant()?->getKey())->pluck('name', 'id'))
                    ->default(fn () => Pipeline::where('sub_account_id', Filament::getTenant()?->getKey())->value('id'))
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('pipeline_stage_id', null))
                    ->required(),
                Select::make('pipeline_stage_id')
                    ->label('Stage')
                    ->options(fn (Get $get) => PipelineStage::where('pipeline_id', $get('pipeline_id'))->orderBy('sort')->pluck('name', 'id'))
                    ->required(),
                Select::make('status')
                    ->options(Opportunity::STATUSES)
                    ->default('open')
                    ->required(),
                Select::make('owner_id')
                    ->label('Assigned to')
                    ->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? []),
                TextInput::make('source'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium'),
                TextColumn::make('contact.full_name')->label('Contact'),
                TextColumn::make('pipeline.name')->label('Pipeline')->toggleable(),
                TextColumn::make('stage.name')->label('Stage')->badge()->color(fn (Opportunity $record) => $record->stage?->color ?? 'gray'),
                TextColumn::make('value')->money('USD')->sortable()->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->money('USD')),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Opportunity::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'won' => 'success',
                        'lost' => 'danger',
                        'abandoned' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('owner.name')->label('Assigned to')->toggleable(),
                TextColumn::make('created_at')->since()->label('Created')->sortable(),
            ])
            ->filters([
                SelectFilter::make('pipeline_id')
                    ->label('Pipeline')
                    ->options(fn () => Pipeline::where('sub_account_id', Filament::getTenant()?->getKey())->pluck('name', 'id')),
                SelectFilter::make('status')->options(Opportunity::STATUSES),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOpportunities::route('/'),
        ];
    }
}
