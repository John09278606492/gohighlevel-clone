<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SubAccount;
use Illuminate\Support\Facades\Http;

/** Stripe Checkout over the REST API (no SDK), with each sub-account's own secret key. */
class Stripe
{
    /** @return array{id: string, url: string} */
    public static function checkoutSession(SubAccount $account, Invoice $invoice): array
    {
        $response = Http::asForm()
            ->withToken($account->stripe_secret_key)
            ->timeout(20)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => (string) $invoice->id,
                'customer_email' => $invoice->contact?->email,
                'metadata' => ['invoice_id' => $invoice->id, 'invoice_token' => $invoice->token],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($invoice->currency),
                        'unit_amount' => (int) round($invoice->balance() * 100),
                        'product_data' => ['name' => "Invoice {$invoice->number} · {$account->name}"],
                    ],
                ]],
                'success_url' => route('invoices.paid', $invoice->token).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('invoices.public', $invoice->token),
            ]);

        if ($response->failed()) {
            abort(502, 'Stripe: '.($response->json('error.message') ?? 'checkout could not be started'));
        }

        return ['id' => (string) $response->json('id'), 'url' => (string) $response->json('url')];
    }

    /** Look the session up at Stripe and record the payment if it was paid. Safe to call more than once. */
    public static function settle(SubAccount $account, string $sessionId): bool
    {
        $response = Http::withToken($account->stripe_secret_key)->timeout(20)
            ->get('https://api.stripe.com/v1/checkout/sessions/'.urlencode($sessionId));

        if ($response->failed() || $response->json('payment_status') !== 'paid') {
            return false;
        }

        $invoice = Invoice::where('sub_account_id', $account->id)->find($response->json('metadata.invoice_id'));

        if (! $invoice) {
            return false;
        }

        $invoice->recordPayment($response->json('amount_total') / 100, 'stripe', $sessionId);

        return true;
    }
}
