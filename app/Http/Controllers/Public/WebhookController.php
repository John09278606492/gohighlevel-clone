<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\SubAccount;
use App\Services\InboundMessages;
use App\Services\Twilio;
use Illuminate\Http\Request;

/** Provider webhooks: Twilio inbound SMS and delivery status, and inbound email. */
class WebhookController extends Controller
{
    public function twilioSms(Request $request, SubAccount $subAccount)
    {
        abort_unless(Twilio::validSignature($subAccount, $request), 403, 'Invalid Twilio signature.');

        InboundMessages::receive(
            $subAccount,
            ['phone' => (string) $request->input('From')],
            'sms',
            (string) $request->input('Body', ''),
            externalId: $request->input('MessageSid'),
            source: 'Inbound SMS',
        );

        // Empty TwiML: no automatic reply (workflows decide what to send).
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml']);
    }

    public function twilioStatus(Request $request, SubAccount $subAccount)
    {
        abort_unless(Twilio::validSignature($subAccount, $request), 403);

        $status = (string) $request->input('MessageStatus');
        $message = Message::where('external_id', $request->input('MessageSid'))->first();

        if ($message && $message->conversation->sub_account_id === $subAccount->id) {
            $message->update([
                'status' => match ($status) {
                    'delivered' => 'delivered',
                    'failed', 'undelivered' => 'failed',
                    default => $message->status,
                },
                'error' => in_array($status, ['failed', 'undelivered'], true) ? 'Twilio error '.$request->input('ErrorCode') : $message->error,
            ]);
        }

        return response()->noContent();
    }

    /**
     * Generic inbound email: point Mailgun / Postmark / SendGrid inbound parse (or a
     * mailbox forwarder) here. Accepts from/sender, subject and text/body-plain fields.
     */
    public function inboundEmail(Request $request, SubAccount $subAccount, string $secret)
    {
        abort_unless(hash_equals((string) $subAccount->inbound_secret, $secret), 403);

        $from = (string) ($request->input('from') ?? $request->input('sender') ?? $request->input('From') ?? '');
        preg_match('/[\w.+\-]+@[\w\-]+\.[\w.\-]+/', $from, $m);
        abort_unless(isset($m[0]), 422, 'No sender address.');

        $name = trim(preg_replace('/<.*>/', '', $from), " \"'");

        InboundMessages::receive(
            $subAccount,
            ['email' => strtolower($m[0])] + ($name && ! str_contains($name, '@') ? ['first_name' => explode(' ', $name)[0]] : []),
            'email',
            (string) ($request->input('stripped-text') ?? $request->input('text') ?? $request->input('body-plain') ?? $request->input('TextBody') ?? strip_tags((string) $request->input('html', ''))),
            (string) ($request->input('subject') ?? $request->input('Subject') ?? ''),
            externalId: $request->input('Message-Id') ?? $request->input('MessageID'),
            source: 'Inbound email',
        );

        return response()->json(['ok' => true]);
    }
}
