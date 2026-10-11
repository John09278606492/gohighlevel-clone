<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ReviewRequest;

class ReviewRequests
{
    public const DEFAULT_MESSAGE = "Hi {{ contact.first_name }}, thanks for choosing {{ business.name }}! Would you take a minute to tell us how we did? {{ review_link }}";

    /** Send a review request by email or SMS. Returns null when the contact can't be reached on that channel. */
    public static function send(Contact $contact, ?string $channel = null): ?ReviewRequest
    {
        $channel ??= $contact->canReceive('sms') ? 'sms' : 'email';

        if (! $contact->canReceive($channel)) {
            return null;
        }

        $request = ReviewRequest::create([
            'sub_account_id' => $contact->sub_account_id,
            'contact_id' => $contact->id,
            'channel' => $channel,
        ]);

        $template = $contact->subAccount->review_request_message ?: self::DEFAULT_MESSAGE;
        $body = str_replace(['{{ review_link }}', '{{review_link}}'], $request->publicUrl(), $template);

        $message = MessageSender::send($contact, $channel, $body, 'How did we do?');
        $request->update(['sent_at' => $message->status === 'failed' ? null : now()]);

        return $request;
    }
}
