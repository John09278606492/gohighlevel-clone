<?php

namespace App\Filament\App\Resources\Tasks;

use App\Filament\App\Resources\Tasks\Pages\ManageTasks;
use App\Models\Contact;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $due = Task::where('sub_account_id', Filament::getTenant()?->getKey())
            ->where('is_completed', false)
            ->where('due_at', '<=', now()->endOfDay())
            ->count();

        return $due ? (string) $due : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->required()->columnSpanFull(),
                Select::make('contact_id')
                    ->label('Contact')
                    ->relationship('contact', 'email', modifyQueryUsing: fn ($query) => $query->where('sub_account_id', Filament::getTenant()?->getKey()))
                    ->getOptionLabelFromRecordUsing(fn (Contact $record) => $record->full_name)
                    ->searchable(['first_name', 'last_name', 'email']),
                Select::make('assigned_to')
                    ->label('Assigned to')
                    ->default(fn () => auth()->id())
                    ->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? []),
                DateTimePicker::make('due_at')->seconds(false),
                Textarea::make('description')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_at')
            ->columns([
                CheckboxColumn::make('is_completed')->label('Done'),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('contact.full_name')->label('Contact'),
                TextColumn::make('due_at')->dateTime()->sortable()
                    ->color(fn (Task $record) => ! $record->is_completed && $record->due_at?->isPast() ? 'danger' : null),
                TextColumn::make('assignee.name')->label('Assigned to'),
            ])
            ->filters([
                TernaryFilter::make('is_completed')->label('Completed')->default(false),
                Filter::make('mine')->label('Assigned to me')->query(fn (Builder $q) => $q->where('assigned_to', auth()->id())),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTasks::route('/')];
    }
}
