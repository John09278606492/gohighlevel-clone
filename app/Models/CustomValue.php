<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A sub-account wide merge value such as {{ custom_values.office_phone }}. */
class CustomValue extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(fn (CustomValue $value) => $value->key = Str::snake(Str::ascii($value->key)));
    }
}
