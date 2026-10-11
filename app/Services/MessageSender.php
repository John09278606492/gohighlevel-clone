<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends an outbound email or SMS to a contact and logs it in their conversation.
 * Email goes through Laravel's mailer (MAIL_* in .env), using the sub-account's
 * "from" name and address. SMS goes through the sub-account's Twilio number; without
 * Twilio credentials it is logged as "queued" so nothing is lost.
 */
class MessageSender
{
    public static function send(Contact $contact, string $channel, string $body, ?string $subject = null, array $options = []): Message
    {
        $account = $contact->subAccount;
        $body = MergeTags::render($body, $contact, $account);
        $subject = $subject ? MergeTags::render($subject, $contact, $account) : null;
        $isHtml = $options['html'] ?? (bool) preg_match('/<(p|br|div|a|h\d|ul|table|img)\b/i', $body);

        $status = 'sent';
        $error = null;
        $externalId = null;

        if ($channel === 'email') {
            if (! $contact->email) {
                [$status, $error] = ['failed', 'The contact has no email address.'];
            } elseif (($options['respect_dnd'] ?? false) && ! $contact->canReceive('email')) {
                [$status, $error] = ['failed', 'The contact unsubscribed from email.'];
            } else {
                try {
                    $html = $isHtml ? $body : nl2br(e($body));
                    if ($options['footer'] ?? false) {
                        $html .= '<p style="margin-top:32px;font-size:12px;color:#888">'.e($account->name)
                            .' · <a href="'.e($contact->unsubscribeUrl()).'" style="color:#888">Unsubscribe</a></p>';
                    }
                    if ($pixel = $options['tracking_pixel'] ?? null) {
                        $html .= '<img src="'.e($pixel).'" width="1" height="1" alt="" style="display:none">';
                    }

                    Mail::html($html, function ($mail) use ($contact, $account, $subject) {
                        $mail->to($contact->email, $contact->full_name)
                            ->subject($subject ?: 'Message from '.$account->name);

                        if ($account->email_from_address) {
                            $mail->from($account->email_from_address, $account->email_from_name ?: $account->name);
                        } elseif ($account->email_from_name) {
                            $mail->from(config('mail.from.address'), $account->email_from_name);
                        }

                        if ($account->email) {
                            $mail->replyTo($account->email, $account->name);
                        }
                    });
                } catch (\Throwable $e) {
                    [$status, $error] = ['failed', Str::limit($e->getMessage(), 250)];
                }
            }
        } elseif ($channel === 'sms') {
            $text = $isHtml ? trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body)))) : $body;

            if (! $contact->phone) {
                [$status, $error] = ['failed', 'The contact has no phone number.'];
            } elseif (($options['respect_dnd'] ?? false) && ! $contact->canReceive('sms')) {
                [$status, $error] = ['failed', 'The contact opted out of SMS.'];
            } elseif ($account->hasTwilio()) {
                try {
                    $result = Twilio::sendSms($account, $contact->phone, $text, route('webhooks.twilio.status', $account));
                    [$status, $externalId] = [$result['status'] === 'failed' ? 'failed' : 'sent', $result['sid']];
                } catch (\Throwable $e) {
                    [$status, $error] = ['failed', Str::limit($e->getMessage(), 250)];
                }
            } else {
                [$status, $error] = ['queued', 'No SMS provider: add Twilio in Settings → Messaging to deliver SMS.'];
            }

            $body = $text;
        } elseif ($channel === 'chat') {
            // Delivered by the website chat widget, which polls the conversation.
            $body = $isHtml ? trim(html_entity_decode(strip_tags($body))) : $body;
        }

        return Message::create([
            'conversation_id' => $contact->conversationOrCreate()->id,
            'user_id' => auth()->id(),
            'campaign_id' => $options['campaign_id'] ?? null,
            'channel' => $channel,
            'direction' => 'outbound',
            'subject' => $subject ?: null,
            'body' => $body,
            'status' => $status,
            'error' => $error,
            'external_id' => $externalId,
        ]);
    }
}
