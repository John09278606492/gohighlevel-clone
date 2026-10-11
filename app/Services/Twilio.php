<?php

namespace App\Services;

use App\Models\SubAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Minimal Twilio REST client (no SDK): send SMS and validate webhook signatures. */
class Twilio
{
    /** @return array{sid: string, status: string} */
    public static function sendSms(SubAccount $account, string $to, string $body, ?string $statusCallback = null): array
    {
        $response = Http::asForm()
            ->withBasicAuth($account->twilio_sid, $account->twilio_token)
            ->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$account->twilio_sid}/Messages.json", array_filter([
                'To' => $to,
                'From' => $account->twilio_from,
                'Body' => $body,
                'StatusCallback' => $statusCallback,
            ]));

        if ($response->failed()) {
            throw new \RuntimeException('Twilio: '.($response->json('message') ?? $response->body()));
        }

        return ['sid' => (string) $response->json('sid'), 'status' => (string) $response->json('status', 'queued')];
    }

    /** https://www.twilio.com/docs/usage/security#validating-requests */
    public static function validSignature(SubAccount $account, Request $request): bool
    {
        if (blank($account->twilio_token)) {
            return false;
        }

        $params = $request->post();
        ksort($params);
        $data = $request->fullUrl();
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $account->twilio_token, true));

        return hash_equals($expected, (string) $request->header('X-Twilio-Signature'));
    }
}
