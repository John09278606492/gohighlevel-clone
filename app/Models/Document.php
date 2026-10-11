<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A proposal, estimate or contract the contact can view and e-sign online. */
class Document extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected $attributes = ['status' => 'draft'];

    public const STATUSES = ['draft' => 'Draft', 'sent' => 'Sent', 'viewed' => 'Viewed', 'signed' => 'Signed', 'declined' => 'Declined'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_on' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Document $d) => $d->token ??= Str::random(40));
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function publicUrl(): string
    {
        return route('documents.public', $this->token);
    }

    public function isExpired(): bool
    {
        return $this->expires_on?->endOfDay()->isPast() ?? false;
    }

    public function canBeSigned(): bool
    {
        return in_array($this->status, ['sent', 'viewed'], true) && ! $this->isExpired();
    }
}
