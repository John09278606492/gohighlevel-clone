<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A copy of a sub-account's setup (not its contacts) that can be loaded into other sub-accounts. */
class Snapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['contents' => 'array', 'payload' => 'array'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SubAccount::class, 'source_sub_account_id');
    }
}
