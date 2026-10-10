<?php

namespace App\Models\Concerns;

use App\Models\SubAccount;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ties a model to a sub-account and fills sub_account_id from the current
 * Filament tenant when it is created inside the sub-account panel.
 */
trait BelongsToSubAccount
{
    public static function bootBelongsToSubAccount(): void
    {
        static::creating(function ($model): void {
            if (blank($model->sub_account_id) && ($tenant = Filament::getTenant()) instanceof SubAccount) {
                $model->sub_account_id = $tenant->getKey();
            }
        });
    }

    public function subAccount(): BelongsTo
    {
        return $this->belongsTo(SubAccount::class);
    }
}
