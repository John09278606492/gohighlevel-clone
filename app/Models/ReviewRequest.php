<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ReviewRequest extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'opened_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (ReviewRequest $r) => $r->token ??= Str::random(40));
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function publicUrl(): string
    {
        return route('reviews.public', $this->token);
    }
}
