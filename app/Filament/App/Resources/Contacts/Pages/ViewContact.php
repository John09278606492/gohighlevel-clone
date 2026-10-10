<?php

namespace App\Filament\App\Resources\Contacts\Pages;

use App\Filament\App\Resources\Contacts\ContactResource;
use App\Filament\App\Resources\Conversations\ConversationResource;
use App\Models\Contact;
use App\Services\MessageSender;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;

class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('message')
                ->label('Send message')
                ->icon('heroicon-o-paper-airplane')
                ->schema([
                    Select::make('channel')
                        ->options(['email' => 'Email', 'sms' => 'SMS'])
                        ->default(fn (Contact $record) => $record->email ? 'email' : 'sms')
                        ->live()
                        ->required(),
                    TextInput::make('subject')->visible(fn (Get $get) => $get('channel') === 'email'),
                    Textarea::make('body')->rows(5)->required(),
                ])
                ->action(function (Contact $record, array $data): void {
                    $message = MessageSender::send($record, $data['channel'], $data['body'], $data['subject'] ?? null);

                    Notification::make()
                        ->title($message->status === 'failed' ? 'Message failed: '.$message->error : 'Message sent')
                        ->{$message->status === 'failed' ? 'danger' : 'success'}()
                        ->send();
                }),
            Action::make('conversation')
                ->label('Open conversation')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url(fn (Contact $record) => ConversationResource::getUrl('view', ['record' => $record->conversationOrCreate()])),
            EditAction::make(),
        ];
    }
}
