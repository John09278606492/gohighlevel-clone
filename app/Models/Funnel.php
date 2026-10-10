<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Funnel extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->orderBy('step_order');
    }
}
