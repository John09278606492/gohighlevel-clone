<?php

namespace App\Filament\App\Resources\SocialPosts;

use App\Filament\App\Resources\SocialPosts\Pages\ManageSocialPosts;
use App\Models\SocialPost;
use App\Services\SocialPublisher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use UnitEnum;

class SocialPostResource extends Resource
{
    protected static ?string $model = SocialPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Social planner';

    protected static ?string $modelLabel = 'post';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Textarea::make('content')->required()->rows(6)->maxLength(3000)->columnSpanFull()
                ->hint(fn (Get $get) => mb_strlen((string) $get('content')).' characters (X allows 280)')
                ->live(debounce: 500),
            CheckboxList::make('platforms')->options(SocialPost::PLATFORMS)->columns(2)->required()->columnSpanFull(),
            FileUpload::make('image')->image()->disk('public')->directory('social'),
            TextInput::make('link')->url(),
            Select::make('status')->options(['draft' => 'Draft', 'scheduled' => 'Scheduled'])->default('scheduled')->required()->live()
                ->visible(fn (?SocialPost $record) => ! in_array($record?->status, ['published', 'failed'], true)),
            DateTimePicker::make('scheduled_at')->label('Publish at')->seconds(false)
                ->required(fn (Get $get) => $get('status') === 'scheduled')
                ->default(now()->addDay()->setTime(9, 0)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_at')
            ->defaultGroup(Group::make('scheduled_at')->label('Day')->date()->collapsible())
            ->columns([
                ImageColumn::make('image')->disk('public')->square()->size(48),
                TextColumn::make('content')->limit(90)->wrap()->searchable(),
                TextColumn::make('platforms')->badge()->formatStateUsing(fn ($state) => SocialPost::PLATFORMS[$state] ?? $state),
                TextColumn::make('scheduled_at')->label('When')->dateTime('M j, g:i A')->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => SocialPost::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'published' => 'success', 'scheduled' => 'warning', 'failed' => 'danger', default => 'gray' })
                    ->description(fn (SocialPost $r) => $r->error),
            ])
            ->filters([SelectFilter::make('status')->options(SocialPost::STATUSES)])
            ->recordActions([
                Action::make('publish')->label('Publish now')->icon('heroicon-o-paper-airplane')
                    ->visible(fn (SocialPost $r) => $r->status !== 'published')
                    ->requiresConfirmation()
                    ->action(function (SocialPost $record) {
                        SocialPublisher::publish($record);
                        Notification::make()->title($record->status === 'published' ? 'Published' : 'Publishing failed: '.$record->error)->{$record->status === 'published' ? 'success' : 'danger'}()->send();
                    }),
                EditAction::make(),
                ReplicateAction::make()->label('Duplicate')->beforeReplicaSaved(fn (SocialPost $replica) => $replica->fill(['status' => 'draft', 'published_at' => null, 'error' => null])),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSocialPosts::route('/')];
    }
}
