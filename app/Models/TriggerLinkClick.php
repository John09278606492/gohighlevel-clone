<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriggerLinkClick extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function link(): BelongsTo
    {
        return $this->belongsTo(TriggerLink::class, 'trigger_link_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
