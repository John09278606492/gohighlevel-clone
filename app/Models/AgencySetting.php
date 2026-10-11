<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Key/value settings for the whole agency (white-label branding and defaults). */
class AgencySetting extends Model
{
    protected $guarded = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_()[$key] ?? $default;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : $value]);
        }

        Cache::forget('agency_settings');
    }

    /** @return array<string, string|null> */
    public static function all_(): array
    {
        return Cache::rememberForever('agency_settings', function () {
            try {
                return Schema::hasTable('agency_settings') ? static::query()->pluck('value', 'key')->all() : [];
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
