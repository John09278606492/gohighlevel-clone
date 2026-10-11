<?php

namespace App\Filament\App\Resources\Contacts\Tables;

use App\Models\Contact;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TagsInput;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Course;
use App\Models\SubAccount;
use App\Services\ContactCsv;
use App\Services\MessageSender;
use App\Services\ReviewRequests;
use App\Support\TenantTags;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Packstub\Flow\Facades\Flow;
use Packstub\Flow\Models\Workflow;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->weight('medium')
                    ->description(fn (Contact $record) => $record->company)
                    ->searchable(['first_name', 'last_name', 'company'])
                    ->sortable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->icon('heroicon-m-envelope')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->icon('heroicon-m-phone')
                    ->searchable()
                    ->toggleable(),
                SpatieTagsColumn::make('tags'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'customer' ? 'success' : 'info'),
                TextColumn::make('source')
                    ->toggleable(),
                TextColumn::make('owner.name')
                    ->label('Assigned to')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_activity_at')
                    ->label('Last activity')
                    ->since()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(['lead' => 'Lead', 'customer' => 'Customer']),
                SelectFilter::make('tag')
                    ->label('Tag')
                    ->multiple()
                    ->options(fn () => TenantTags::options())
                    ->query(fn (Builder $query, array $data) => filled($data['values'] ?? null)
                        ? $query->withAnyTags($data['values'])
                        : $query),
                SelectFilter::make('owner_id')
                    ->label('Assigned to')
                    ->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? []),
                TernaryFilter::make('dnd')
                    ->label('Do not disturb'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('message')
                        ->label('Send email / SMS')
                        ->icon('heroicon-o-paper-airplane')
                        ->schema([
                            Select::make('channel')->options(['email' => 'Email', 'sms' => 'SMS'])->default('email')->live()->required(),
                            TextInput::make('subject')->visible(fn (Get $get) => $get('channel') === 'email'),
                            Textarea::make('body')->rows(5)->required()->helperText('Merge tags like {{ contact.first_name }} work here. Contacts who opted out are skipped.'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $sent = $records->filter(fn (Contact $c) => MessageSender::send($c, $data['channel'], $data['body'], $data['subject'] ?? null, ['respect_dnd' => true])->status !== 'failed')->count();
                            Notification::make()->success()->title("Sent to {$sent} of {$records->count()} contacts")->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('workflow')
                        ->label('Add to workflow')
                        ->icon('heroicon-o-bolt')
                        ->schema([
                            Select::make('workflow')
                                ->options(fn () => Workflow::query()->where('tenant_type', SubAccount::class)->where('tenant_id', (string) Filament::getTenant()?->getKey())->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $workflow = Workflow::query()->where('tenant_type', SubAccount::class)->where('tenant_id', (string) Filament::getTenant()?->getKey())->findOrFail($data['workflow']);
                            $records->each(fn (Contact $c) => Flow::run($workflow, ['model' => $c]));
                            Notification::make()->success()->title("Started \"{$workflow->name}\" for {$records->count()} contacts")->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('addTags')
                        ->label('Add tags')
                        ->icon('heroicon-o-tag')
                        ->schema([TagsInput::make('tags')->suggestions(fn () => array_values(TenantTags::options()))->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->attachTags($data['tags']))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('removeTags')
                        ->label('Remove tags')
                        ->icon('heroicon-o-x-mark')
                        ->schema([Select::make('tags')->multiple()->options(fn () => TenantTags::options())->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->detachTags($data['tags']))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('assign')
                        ->label('Assign to')
                        ->icon('heroicon-o-user-circle')
                        ->schema([Select::make('owner_id')->label('Team member')->options(fn () => Filament::getTenant()?->users()->pluck('name', 'users.id') ?? [])->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['owner_id' => $data['owner_id']]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('type')
                        ->label('Change type')
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema([Select::make('type')->options(['lead' => 'Lead', 'customer' => 'Customer'])->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['type' => $data['type']]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reviewRequest')
                        ->label('Request a review')
                        ->icon('heroicon-o-star')
                        ->requiresConfirmation()
                        ->modalDescription('Sends each contact a link to rate you (SMS if they have a phone, otherwise email).')
                        ->action(function (Collection $records): void {
                            $sent = $records->filter(fn (Contact $c) => ReviewRequests::send($c))->count();
                            Notification::make()->success()->title("Review requests sent to {$sent} contacts")->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('enroll')
                        ->label('Give course access')
                        ->icon('heroicon-o-academic-cap')
                        ->schema([Select::make('course_id')->label('Course')->options(fn () => Course::where('sub_account_id', Filament::getTenant()?->getKey())->pluck('title', 'id'))->required()])
                        ->action(function (Collection $records, array $data): void {
                            $course = Course::where('sub_account_id', Filament::getTenant()?->getKey())->findOrFail($data['course_id']);
                            $records->each(fn (Contact $c) => $course->enroll($c));
                            Notification::make()->success()->title("Enrolled {$records->count()} contacts in {$course->title}")->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('export')
                        ->label('Export to CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Collection $records) => ContactCsv::export($records->load('tags'), Filament::getTenant())),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
