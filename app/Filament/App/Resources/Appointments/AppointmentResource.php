<?php

namespace App\Filament\App\Resources\Appointments;

use App\Filament\App\Resources\Appointments\Pages\ManageAppointments;
use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Contact;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Calendars';

    protected static ?string $navigationLabel = 'Appointments';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('calendar_id')
                    ->label('Calendar')
                    ->options(fn () => Calendar::where('sub_account_id', Filament::getTenant()?->getKey())->pluck('name', 'id'))
                    ->default(fn () => Calendar::where('sub_account_id', Filament::getTenant()?->getKey())->value('id'))
                    ->live()
                    ->required(),
                Select::make('contact_id')
                    ->label('Contact')
                    ->relationship('contact', 'email', modifyQueryUsing: fn ($query) => $query->where('sub_account_id', Filament::getTenant()?->getKey()))
                    ->getOptionLabelFromRecordUsing(fn (Contact $record) => $record->full_name)
                    ->searchable(['first_name', 'last_name', 'email', 'phone'])
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                        if (blank($get('title')) && $state) {
                            $set('title', 'Meeting with '.Contact::find($state)?->full_name);
                        }
                    }),
                TextInput::make('title')->required()->columnSpanFull(),
                DateTimePicker::make('starts_at')
                    ->seconds(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                        $minutes = Calendar::find($get('calendar_id'))?->duration_minutes ?? 30;
                        if ($state) {
                            $set('ends_at', Carbon::parse($state)->addMinutes($minutes)->format('Y-m-d H:i'));
                        }
                    }),
                DateTimePicker::make('ends_at')->seconds(false)->required()->after('starts_at'),
                Select::make('status')->options(Appointment::STATUSES)->default('booked')->required(),
                Select::make('user_id')
                    ->label('Team member')
                    ->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? []),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')->label('When')->dateTime('D, M j · g:i A')->sortable()
                    ->description(fn (Appointment $record) => $record->starts_at->diffForHumans()),
                TextColumn::make('title')->searchable(),
                TextColumn::make('contact.full_name')->label('Contact'),
                TextColumn::make('calendar.name')->label('Calendar')->toggleable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => Appointment::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'confirmed', 'showed' => 'success', 'no_show', 'cancelled' => 'danger', default => 'info',
                    }),
                TextColumn::make('source')->badge()->color('gray')->toggleable(),
            ])
            ->filters([
                Filter::make('upcoming')->query(fn (Builder $q) => $q->where('starts_at', '>=', now()))->default(),
                SelectFilter::make('status')->options(Appointment::STATUSES),
                SelectFilter::make('calendar_id')->label('Calendar')
                    ->options(fn () => Calendar::where('sub_account_id', Filament::getTenant()?->getKey())->pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('showed')->label('Showed')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (Appointment $record) => in_array($record->status, ['booked', 'confirmed']))
                    ->action(fn (Appointment $record) => $record->update(['status' => 'showed'])),
                Action::make('noShow')->label('No show')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Appointment $record) => in_array($record->status, ['booked', 'confirmed']))
                    ->action(fn (Appointment $record) => $record->update(['status' => 'no_show'])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAppointments::route('/'),
        ];
    }
}
