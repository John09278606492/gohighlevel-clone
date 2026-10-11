<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    public const METHODS = [
        'manual' => 'Manual',
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'card' => 'Card (offline)',
        'check' => 'Check',
        'stripe' => 'Stripe',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
