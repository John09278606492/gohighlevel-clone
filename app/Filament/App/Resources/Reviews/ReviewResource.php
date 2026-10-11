<?php

namespace App\Filament\App\Resources\Reviews;

use App\Filament\App\Resources\Reviews\Pages\ManageReviews;
use App\Models\Contact;
use App\Models\Review;
use App\Services\MessageSender;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Reputation';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $unanswered = Review::where('sub_account_id', Access::tenantId())->where('rating', '<=', 3)->whereNull('replied_at')->count();

        return $unanswered ? (string) $unanswered : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('rating')->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★'])->required(),
            Select::make('source')->options(Review::SOURCES)->default('manual')->required(),
            TextInput::make('author_name')->maxLength(120),
            Select::make('contact_id')->label('Contact')
                ->relationship('contact', 'email', modifyQueryUsing: fn ($query) => $query->where('sub_account_id', Access::tenantId()))
                ->getOptionLabelFromRecordUsing(fn (Contact $r) => $r->full_name)
                ->searchable(['first_name', 'last_name', 'email']),
            Textarea::make('comment')->rows(3)->columnSpanFull(),
            Toggle::make('is_public')->label('Show on website ([reviews] shortcode)')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')->formatStateUsing(fn ($state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn ($state) => $state >= 4 ? 'warning' : 'danger')->sortable(),
                TextColumn::make('author_name')->label('From')->description(fn (Review $r) => Review::SOURCES[$r->source] ?? $r->source),
                TextColumn::make('comment')->wrap()->limit(160)->description(fn (Review $r) => $r->reply ? 'Replied: '.str($r->reply)->limit(80) : null),
                IconColumn::make('is_public')->label('Public')->boolean(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('rating')->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star']),
                TernaryFilter::make('replied_at')->label('Replied')->nullable(),
            ])
            ->recordActions([
                Action::make('reply')->icon('heroicon-o-chat-bubble-left')
                    ->schema(fn (Review $record) => [
                        Textarea::make('reply')->default($record->reply)->rows(4)->required(),
                        Toggle::make('send')->label('Also send it to the contact')->default(true)->visible((bool) $record->contact),
                    ])
                    ->action(function (Review $record, array $data): void {
                        $record->update(['reply' => $data['reply'], 'replied_at' => now()]);
                        if (($data['send'] ?? false) && $record->contact) {
                            $channel = $record->contact->canReceive('email') ? 'email' : 'sms';
                            MessageSender::send($record->contact, $channel, $data['reply'], 'Thanks for your feedback');
                        }
                        Notification::make()->success()->title('Reply saved')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReviews::route('/')];
    }
}
