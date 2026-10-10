<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'is_completed' => 'boolean'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
