<?php

namespace App\Filament\App\Resources\SmartLists;

use App\Filament\App\Resources\Contacts\ContactResource;
use App\Filament\App\Resources\SmartLists\Pages\ManageSmartLists;
use App\Models\CustomField;
use App\Models\SmartList;
use App\Support\Access;
use App\Support\TenantTags;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Saved contact segments. They show as tabs on Contacts and can be a campaign's audience. */
class SmartListResource extends Resource
{
    protected static ?string $model = SmartList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Smart lists';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(fn ($q) => $q->where('is_shared', true)->orWhere('user_id', auth()->id()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(100),
            Toggle::make('is_shared')->label('Visible to the whole team')->default(true)->inline(false),
            Section::make('Contacts that match all of these')->columnSpanFull()->columns(2)->schema(static::criteriaFields()),
        ]);
    }

    /** @return array<\Filament\Forms\Components\Field> */
    public static function criteriaFields(string $prefix = 'criteria.'): array
    {
        $tags = fn () => TenantTags::options();

        return [
            Select::make($prefix.'tags_any')->label('Has any of these tags')->multiple()->options($tags)->searchable(),
            Select::make($prefix.'tags_all')->label('Has all of these tags')->multiple()->options($tags)->searchable(),
            Select::make($prefix.'tags_none')->label('Has none of these tags')->multiple()->options($tags)->searchable(),
            Select::make($prefix.'type')->options(['lead' => 'Lead', 'customer' => 'Customer']),
            TextInput::make($prefix.'source')->label('Source contains'),
            Select::make($prefix.'owner_id')->label('Assigned to')->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? []),
            Select::make($prefix.'has_email')->label('Email')->options(['1' => 'Has an email', '0' => 'No email']),
            Select::make($prefix.'has_phone')->label('Phone')->options(['1' => 'Has a phone', '0' => 'No phone']),
            TextInput::make($prefix.'created_within_days')->label('Added in the last … days')->numeric()->minValue(1),
            TextInput::make($prefix.'inactive_for_days')->label('No activity for … days')->numeric()->minValue(1),
            Toggle::make($prefix.'has_open_opportunity')->label('Has an open opportunity'),
            Toggle::make($prefix.'has_upcoming_appointment')->label('Has an upcoming appointment'),
            Toggle::make($prefix.'exclude_dnd')->label('Leave out Do Not Disturb'),
            Select::make($prefix.'custom_field')->label('Custom field is set')
                ->options(fn () => CustomField::where('sub_account_id', Access::tenantId())->pluck('name', 'key')),
            TextInput::make($prefix.'custom_value')->label('…and equals (optional)'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->weight('medium')->searchable(),
                TextColumn::make('size')->label('Contacts')->state(fn (SmartList $record) => $record->contacts()->count()),
                IconColumn::make('is_shared')->label('Shared')->boolean(),
                TextColumn::make('user.name')->label('Created by'),
            ])
            ->recordActions([
                Action::make('open')->label('Open')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SmartList $record) => ContactResource::getUrl('index', ['tab' => 'list-'.$record->id])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSmartLists::route('/')];
    }
}
