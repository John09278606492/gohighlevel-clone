<?php

namespace App\Flow\Actions;

use App\Services\MessageSender;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Packstub\Flow\Exceptions\WorkflowException;
use Packstub\Flow\Nodes\Action;
use Packstub\Flow\Nodes\Concerns\InterpolatesPlaceholders;

/**
 * Sends an email or SMS to the run's contact and records it in their
 * conversation, so it shows up in the Conversations inbox.
 */
class SendContactMessage extends Action
{
    use InterpolatesPlaceholders, ResolvesContact;

    public function getName(): string
    {
        return 'Send message to contact';
    }

    public function getDescription(): string
    {
        return 'Email or SMS the contact; it is logged in their conversation.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function getCategory(): string
    {
        return 'CRM';
    }

    public function getFormSchema(): array
    {
        return [
            Select::make('channel')
                ->options(['email' => 'Email', 'sms' => 'SMS'])
                ->default('email')
                ->live()
                ->required(),
            TextInput::make('subject')
                ->placeholder('Thanks for reaching out, {{ model.first_name }}')
                ->visible(fn (Get $get) => $get('channel') === 'email'),
            Textarea::make('body')
                ->rows(6)
                ->placeholder("Hi {{ model.first_name }},\n\n...")
                ->required(),
        ];
    }

    public function handle(array $config, array $payload): void
    {
        $contact = $this->contactFrom($payload);

        if (! $contact) {
            throw new WorkflowException('Send message to contact needs a contact, opportunity or appointment record.');
        }

        if ($contact->dnd) {
            $this->output(['skipped' => 'Contact is on Do Not Disturb']);

            return;
        }

        $channel = $config['channel'] ?? 'email';
        $message = MessageSender::send(
            $contact,
            $channel,
            $this->interpolate($config['body'] ?? '', $payload),
            $this->interpolate($config['subject'] ?? '', $payload) ?: null,
        );

        if ($message->status === 'failed') {
            throw new WorkflowException($message->error ?? 'Sending failed.');
        }

        $status = $message->status;
        $this->output(['channel' => $channel, 'status' => $status, 'to' => $channel === 'email' ? $contact->email : $contact->phone]);
    }
}
