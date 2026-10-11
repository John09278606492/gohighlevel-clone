<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\SubAccount;
use App\Services\Stripe;
use Illuminate\Http\Request;

/** The client-facing invoice page and Stripe Checkout. */
class InvoiceController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invoice = Invoice::with(['items', 'subAccount', 'contact', 'payments'])->where('token', $token)->firstOrFail();
        abort_if($invoice->status === 'draft' && ! $request->user()?->canAccessTenant($invoice->subAccount), 404);

        if (! $invoice->viewed_at && ! $request->user()) {
            $invoice->forceFill(['viewed_at' => now()])->saveQuietly();
            Activity::log($invoice->contact, 'invoice', "Viewed invoice {$invoice->number}", $invoice);
        }

        return view('public.invoice', ['invoice' => $invoice]);
    }

    public function pay(string $token)
    {
        $invoice = Invoice::where('token', $token)->firstOrFail();
        $account = $invoice->subAccount;

        abort_if(in_array($invoice->status, ['draft', 'void', 'paid'], true) || $invoice->balance() <= 0, 422, 'This invoice can not be paid online.');
        abort_unless($account->hasStripe(), 422, 'Online payments are not set up for this business.');

        $session = Stripe::checkoutSession($account, $invoice);

        return redirect()->away($session['url']);
    }

    /** Stripe redirects here after checkout; we confirm with Stripe before recording anything. */
    public function paid(Request $request, string $token)
    {
        $invoice = Invoice::where('token', $token)->firstOrFail();

        if ($sessionId = $request->query('session_id')) {
            Stripe::settle($invoice->subAccount, (string) $sessionId);
        }

        return redirect()->route('invoices.public', $token)->with('paid', true);
    }

    /** Stripe webhook (checkout.session.completed). The session is re-fetched from Stripe, so the body is never trusted. */
    public function stripeWebhook(Request $request, SubAccount $subAccount)
    {
        $type = $request->input('type');
        $sessionId = $request->input('data.object.id');

        if ($type === 'checkout.session.completed' && is_string($sessionId) && $subAccount->hasStripe()) {
            Stripe::settle($subAccount, $sessionId);
        }

        return response()->json(['received' => true]);
    }
}
