<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('sort');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }
}
