<?php

namespace App\Support;

use App\Models\AgencySetting;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;

/** White-label branding set by the agency in Agency → Branding. */
class Branding
{
    public static function name(): string
    {
        return AgencySetting::get('brand_name') ?: config('app.name');
    }

    public static function logoUrl(): ?string
    {
        $logo = AgencySetting::get('brand_logo');

        return $logo ? Storage::disk('public')->url($logo) : null;
    }

    public static function faviconUrl(): ?string
    {
        $icon = AgencySetting::get('brand_favicon');

        return $icon ? Storage::disk('public')->url($icon) : null;
    }

    /** @return array<string, array<int, string>> */
    public static function primary(array $fallback = Color::Indigo): array
    {
        $hex = AgencySetting::get('brand_color');

        if ($hex && preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
            try {
                return Color::hex($hex);
            } catch (\Throwable) {
                // fall through
            }
        }

        return $fallback;
    }
}
