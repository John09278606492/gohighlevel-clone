<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A sub-account specific contact field, stored in contacts.custom_fields under its key. */
class CustomField extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    public const TYPES = [
        'text' => 'Single line',
        'textarea' => 'Multi line',
        'number' => 'Number',
        'date' => 'Date',
        'select' => 'Dropdown',
        'checkbox' => 'Checkbox',
        'url' => 'URL',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (CustomField $field): void {
            $field->key = Str::snake(Str::ascii($field->key ?: $field->name)) ?: 'field';
        });
    }
}
