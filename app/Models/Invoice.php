<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use App\Support\CrmEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected $attributes = ['status' => 'draft'];

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
        'void' => 'Void',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            $invoice->token ??= Str::random(40);
            $invoice->issue_date ??= now();
            $invoice->number ??= static::nextNumber($invoice->sub_account_id);

            if ($account = SubAccount::find($invoice->sub_account_id)) {
                $invoice->currency ??= $account->currency;
            }
        });
    }

    public static function nextNumber(?int $subAccountId): string
    {
        $last = static::where('sub_account_id', $subAccountId)->pluck('number')
            ->map(fn ($n) => (int) preg_replace('/\D/', '', (string) $n))->max() ?? 0;

        return 'INV-'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_at');
    }

    public function balance(): float
    {
        return max(0, round((float) $this->total - (float) $this->amount_paid, 2));
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['sent', 'partially_paid'], true) && $this->due_date?->isPast() && ! $this->due_date->isToday();
    }

    public function publicUrl(): string
    {
        return route('invoices.public', $this->token);
    }

    /** Recompute line amounts and totals from the items. */
    public function recalculate(): void
    {
        $subtotal = 0.0;

        foreach ($this->items()->get() as $item) {
            $amount = round((float) $item->quantity * (float) $item->unit_price, 2);
            if ((float) $item->amount !== $amount) {
                $item->forceFill(['amount' => $amount])->saveQuietly();
            }
            $subtotal += $amount;
        }

        $taxable = max(0, $subtotal - (float) $this->discount);
        $tax = round($taxable * (float) $this->tax_rate / 100, 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => round($taxable + $tax, 2),
        ]);
        $this->status = $this->statusFromPayments();
        $this->saveQuietly();
    }

    protected function statusFromPayments(): string
    {
        if ($this->status === 'void' || $this->status === 'draft' && (float) $this->amount_paid <= 0) {
            return $this->status;
        }

        return match (true) {
            (float) $this->total > 0 && (float) $this->amount_paid >= (float) $this->total => 'paid',
            (float) $this->amount_paid > 0 => 'partially_paid',
            default => $this->status === 'paid' ? 'sent' : $this->status,
        };
    }

    /**
     * Record money received against the invoice. Idempotent per (method, reference),
     * so a Stripe webhook and the redirect back from checkout can't double count.
     */
    public function recordPayment(float $amount, string $method = 'manual', ?string $reference = null, ?string $notes = null): ?Payment
    {
        return DB::transaction(function () use ($amount, $method, $reference, $notes) {
            if ($reference && Payment::where('method', $method)->where('reference', $reference)->exists()) {
                return null;
            }

            $payment = $this->payments()->create([
                'sub_account_id' => $this->sub_account_id,
                'contact_id' => $this->contact_id,
                'amount' => round($amount, 2),
                'currency' => $this->currency,
                'method' => $method,
                'reference' => $reference,
                'notes' => $notes,
                'paid_at' => now(),
            ]);

            $wasPaid = $this->status === 'paid';
            $this->amount_paid = round((float) $this->amount_paid + $amount, 2);
            if ($this->status === 'draft') {
                $this->status = 'sent';
            }
            $this->status = $this->statusFromPayments();
            if ($this->status === 'paid' && ! $wasPaid) {
                $this->paid_at = now();
            }
            $this->saveQuietly();

            $currency = $this->currency;
            Activity::log($this->contact, 'payment', "Paid {$currency} ".number_format($amount, 2)." on invoice {$this->number}", $this, ['method' => $method]);

            if ($this->status === 'paid' && ! $wasPaid) {
                $this->fulfil();
                CrmEvents::fire('invoice_paid', $this->contact, $this, ['invoice' => $this->only(['number', 'total', 'currency'])]);
            }

            return $payment;
        });
    }

    /** Grant anything the invoice paid for (course access for course products). */
    public function fulfil(): void
    {
        if (! $this->contact) {
            return;
        }

        foreach ($this->items()->with('product.course')->get() as $item) {
            if ($course = $item->product?->course) {
                $course->enroll($this->contact, 'purchase');
            }
        }

        if ($this->contact->type !== 'customer') {
            $this->contact->forceFill(['type' => 'customer'])->saveQuietly();
        }
    }
}
