<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    public const SOURCES = ['direct' => 'Review page', 'google' => 'Google', 'facebook' => 'Facebook', 'manual' => 'Added manually'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'replied_at' => 'datetime', 'rating' => 'integer'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReviewRequest::class, 'review_request_id');
    }
}
