<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Message;
use App\Models\SubAccount;
use App\Support\CrmEvents;

/** Everything that arrives from outside (SMS, email, web chat) goes through here. */
class InboundMessages
{
    public const STOP_WORDS = ['stop', 'stopall', 'unsubscribe', 'cancel', 'end', 'quit'];

    public const START_WORDS = ['start', 'unstop', 'yes'];

    public static function receive(SubAccount $account, array $contactAttributes, string $channel, string $body, ?string $subject = null, ?string $externalId = null, string $source = 'Inbound'): Message
    {
        if ($externalId && ($existing = Message::where('external_id', $externalId)->first())) {
            return $existing; // provider retried the webhook
        }

        $contact = Contact::upsertFor($account, $contactAttributes + ['source' => $source]);

        $message = Message::create([
            'conversation_id' => $contact->conversationOrCreate()->id,
            'channel' => $channel,
            'direction' => 'inbound',
            'subject' => $subject,
            'body' => $body,
            'status' => 'received',
            'external_id' => $externalId,
        ]);

        if ($channel === 'sms') {
            $word = strtolower(trim($body));

            if (in_array($word, self::STOP_WORDS, true)) {
                $contact->forceFill(['dnd_sms' => true])->save();
                Activity::log($contact, 'dnd', 'Opted out of SMS by replying '.strtoupper($word));
                CrmEvents::fire('contact_unsubscribed', $contact, null, ['channel' => 'sms']);
            } elseif (in_array($word, self::START_WORDS, true) && $contact->dnd_sms) {
                $contact->forceFill(['dnd_sms' => false])->save();
                Activity::log($contact, 'dnd', 'Opted back in to SMS');
            }
        }

        CrmEvents::fire('inbound_message', $contact, $message, ['channel' => $channel, 'body' => $body, 'subject' => $subject]);

        return $message;
    }
}
