<?php

namespace App\Flow\Actions;

use App\Models\Contact;
use App\Services\ReviewRequests;
use Filament\Forms\Components\Select;

class SendReviewRequest extends ContactAction
{
    public function getName(): string
    {
        return 'Send review request';
    }

    public function getDescription(): string
    {
        return 'Asks the contact to rate you; happy customers are sent to Google / Facebook.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-star';
    }

    public function getFormSchema(): array
    {
        return [Select::make('channel')->options(['sms' => 'SMS', 'email' => 'Email'])->placeholder('Best available')];
    }

    protected function run(Contact $contact, array $config, array $payload): array
    {
        $request = ReviewRequests::send($contact, $config['channel'] ?? null);

        return $request ? ['review_request_id' => $request->id, 'channel' => $request->channel] : ['skipped' => 'Contact can\'t be reached'];
    }
}
