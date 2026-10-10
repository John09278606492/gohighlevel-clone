<?php

namespace App\Filament\App\Resources\Conversations\Pages;

use App\Filament\App\Resources\Contacts\ContactResource;
use App\Filament\App\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageSender;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A chat-style thread of every email, SMS and internal note with one contact,
 * with a composer at the bottom.
 *
 * @property Conversation $record
 */
class ViewConversation extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ConversationResource::class;

    protected string $view = 'filament.app.conversations.view';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->update(['unread_count' => 0]);
        $this->form->fill(['channel' => $this->record->contact->email ? 'email' : 'sms']);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->contact->full_name;
    }

    public function getSubheading(): ?string
    {
        return collect([$this->record->contact->email, $this->record->contact->phone])->filter()->implode(' · ') ?: null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(4)->schema([
                    Select::make('channel')
                        ->options(['email' => 'Email', 'sms' => 'SMS', 'note' => 'Internal note'])
                        ->selectablePlaceholder(false)
                        ->live()
                        ->required(),
                    TextInput::make('subject')
                        ->placeholder('Subject')
                        ->columnSpan(3)
                        ->visible(fn (Get $get) => $get('channel') === 'email'),
                ]),
                Textarea::make('body')
                    ->hiddenLabel()
                    ->placeholder('Type a message…')
                    ->rows(3)
                    ->required(),
            ]);
    }

    public function send(): void
    {
        $data = $this->form->getState();

        if ($data['channel'] === 'note') {
            Message::create([
                'conversation_id' => $this->record->id,
                'user_id' => auth()->id(),
                'channel' => 'note',
                'direction' => 'outbound',
                'body' => $data['body'],
                'status' => 'sent',
            ]);
        } else {
            $message = MessageSender::send($this->record->contact, $data['channel'], $data['body'], $data['subject'] ?? null);

            if ($message->status === 'failed') {
                Notification::make()->danger()->title('Not sent')->body($message->error)->send();
            }
        }

        $this->form->fill(['channel' => $data['channel']]);
        $this->record->refresh();
    }

    /** Lets you record a reply the contact sent through another channel (or test the inbox). */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('logInbound')
                ->label('Log inbound message')
                ->icon('heroicon-o-arrow-down-left')
                ->color('gray')
                ->schema([
                    Select::make('channel')->options(['email' => 'Email', 'sms' => 'SMS'])->default('sms')->required(),
                    Textarea::make('body')->required(),
                ])
                ->action(function (array $data): void {
                    Message::create($data + ['conversation_id' => $this->record->id, 'direction' => 'inbound', 'status' => 'received']);
                    $this->record->update(['unread_count' => 0]);
                }),
            Action::make('contact')
                ->label('View contact')
                ->icon('heroicon-o-user')
                ->url(fn () => ContactResource::getUrl('view', ['record' => $this->record->contact])),
        ];
    }

    public function getMessages()
    {
        return $this->record->messages()->with('user')->get();
    }
}
