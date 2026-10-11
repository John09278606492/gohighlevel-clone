<?php

namespace App\Filament\App\Resources\Contacts\Pages;

use App\Filament\App\Resources\Contacts\ContactResource;
use App\Filament\App\Resources\Conversations\ConversationResource;
use App\Filament\App\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use App\Filament\App\Resources\Documents\DocumentResource;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Models\Contact;
use App\Models\Course;
use App\Services\ReviewRequests;
use Filament\Actions\ActionGroup;
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
                    $message = MessageSender::send($record, $data['channel'], $data['body'], $data['subject'] ?? null, ['respect_dnd' => true]);

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
            ActionGroup::make([
                Action::make('invoice')->label('New invoice')->icon('heroicon-o-document-text')
                    ->url(fn (Contact $record) => InvoiceResource::getUrl('create', ['contact' => $record->id])),
                Action::make('document')->label('New proposal / contract')->icon('heroicon-o-document-check')
                    ->url(fn (Contact $record) => DocumentResource::getUrl('create', ['contact' => $record->id])),
                Action::make('review')->label('Request a review')->icon('heroicon-o-star')
                    ->requiresConfirmation()
                    ->action(function (Contact $record): void {
                        $request = ReviewRequests::send($record);
                        Notification::make()->title($request ? 'Review request sent' : 'This contact can\'t be messaged')->{$request ? 'success' : 'warning'}()->send();
                    }),
                Action::make('course')->label('Give course access')->icon('heroicon-o-academic-cap')
                    ->schema([Select::make('course_id')->label('Course')->options(fn (Contact $record) => Course::where('sub_account_id', $record->sub_account_id)->pluck('title', 'id'))->required()])
                    ->action(function (Contact $record, array $data): void {
                        $enrollment = Course::where('sub_account_id', $record->sub_account_id)->findOrFail($data['course_id'])->enroll($record);
                        EnrollmentsRelationManager::sendAccess($enrollment);
                        Notification::make()->success()->title('Access granted and link sent')->send();
                    }),
            ])->label('More')->icon('heroicon-m-ellipsis-vertical')->button()->color('gray'),
            EditAction::make(),
        ];
    }
}
