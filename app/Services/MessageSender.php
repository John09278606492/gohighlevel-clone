<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Sends an outbound email or SMS to a contact and logs it in their conversation.
 * Email goes through Laravel's mailer (configure MAIL_* in .env). SMS is logged as
 * "queued": plug a provider in here, or use Flow's built-in Twilio "Send SMS" action.
 */
class MessageSender
{
    public static function send(Contact $contact, string $channel, string $body, ?string $subject = null): Message
    {
        $status = 'sent';
        $error = null;

        if ($channel === 'email') {
            if (! $contact->email) {
                [$status, $error] = ['failed', 'The contact has no email address.'];
            } else {
                try {
                    Mail::raw($body, fn ($mail) => $mail
                        ->to($contact->email, $contact->full_name)
                        ->subject($subject ?: 'Message from '.$contact->subAccount->name));
                } catch (\Throwable $e) {
                    [$status, $error] = ['failed', $e->getMessage()];
                }
            }
        } elseif ($channel === 'sms') {
            [$status, $error] = $contact->phone ? ['queued', null] : ['failed', 'The contact has no phone number.'];
        }

        return Message::create([
            'conversation_id' => $contact->conversationOrCreate()->id,
            'user_id' => auth()->id(),
            'channel' => $channel,
            'direction' => 'outbound',
            'subject' => $subject ?: null,
            'body' => $body,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
