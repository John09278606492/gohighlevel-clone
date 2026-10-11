@php($account = $invoice->subAccount)
@php($money = fn ($v) => $invoice->currency.' '.number_format((float) $v, 2))
<x-layouts.public :title="'Invoice '.$invoice->number.' · '.$account->name">
    <main class="ghl-doc">
        @include('public.partials.brand', ['account' => $account])

        @if (session('paid') || $invoice->status === 'paid')
            <div class="ghl-alert ghl-alert--success">{{ $invoice->status === 'paid' ? 'Paid in full. Thank you!' : 'Thanks! Your payment is being confirmed.' }}</div>
        @elseif ($invoice->status === 'void')
            <div class="ghl-alert ghl-alert--danger">This invoice was voided.</div>
        @elseif ($invoice->isOverdue())
            <div class="ghl-alert ghl-alert--warning">This invoice was due {{ $invoice->due_date->format('M j, Y') }}.</div>
        @endif

        <section class="ghl-card">
            <div class="ghl-doc__head">
                <div>
                    <h1>Invoice {{ $invoice->number }}</h1>
                    @if ($invoice->title)<p class="ghl-doc__muted">{{ $invoice->title }}</p>@endif
                </div>
                <dl class="ghl-doc__meta">
                    <dt>Issued</dt><dd>{{ $invoice->issue_date->format('M j, Y') }}</dd>
                    @if ($invoice->due_date)<dt>Due</dt><dd>{{ $invoice->due_date->format('M j, Y') }}</dd>@endif
                    @if ($invoice->contact)<dt>Billed to</dt><dd>{{ $invoice->contact->full_name }}@if ($invoice->contact->company)<br>{{ $invoice->contact->company }}@endif</dd>@endif
                </dl>
            </div>

            <table class="ghl-table">
                <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr><td>{{ $item->description }}</td><td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td><td class="num">{{ $money($item->unit_price) }}</td><td class="num">{{ $money($item->amount) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="3">Subtotal</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
                    @if ((float) $invoice->discount > 0)<tr><td colspan="3">Discount</td><td class="num">−{{ $money($invoice->discount) }}</td></tr>@endif
                    @if ((float) $invoice->tax > 0)<tr><td colspan="3">Tax ({{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}%)</td><td class="num">{{ $money($invoice->tax) }}</td></tr>@endif
                    <tr class="ghl-table__total"><td colspan="3">Total</td><td class="num">{{ $money($invoice->total) }}</td></tr>
                    @if ((float) $invoice->amount_paid > 0)
                        <tr><td colspan="3">Paid</td><td class="num">−{{ $money($invoice->amount_paid) }}</td></tr>
                        <tr class="ghl-table__total"><td colspan="3">Balance due</td><td class="num">{{ $money($invoice->balance()) }}</td></tr>
                    @endif
                </tfoot>
            </table>

            @if ($invoice->notes)
                <p class="ghl-doc__notes">{!! nl2br(e($invoice->notes)) !!}</p>
            @endif

            @if (in_array($invoice->status, ['sent', 'partially_paid'], true) && $invoice->balance() > 0)
                <div class="ghl-doc__actions">
                    @if ($account->hasStripe())
                        <form method="post" action="{{ route('invoices.pay', $invoice->token) }}">
                            @csrf
                            <button type="submit" class="ghl-button">Pay {{ $money($invoice->balance()) }}</button>
                        </form>
                    @else
                        <p class="ghl-doc__muted">To pay, contact {{ $account->name }}{{ $account->phone ? ' at '.$account->phone : '' }}.</p>
                    @endif
                </div>
            @endif
        </section>

        @if ($account->invoice_footer)
            <p class="ghl-doc__footer">{!! nl2br(e($account->invoice_footer)) !!}</p>
        @endif
    </main>
</x-layouts.public>
