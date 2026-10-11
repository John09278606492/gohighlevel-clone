<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A tracked short link (/l/{code}). Used in messages it knows who clicked
 * (?c={contact token}), tags them and fires "Trigger link clicked" workflows.
 */
class TriggerLink extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['add_tags' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (TriggerLink $link): void {
            do {
                $link->code ??= Str::lower(Str::random(8));
            } while (static::where('code', $link->code)->exists() && ! ($link->code = null));
        });
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(TriggerLinkClick::class);
    }

    public function shortUrl(?Contact $contact = null): string
    {
        return route('links.go', ['code' => $this->code] + ($contact ? ['c' => $contact->trackingToken()] : []));
    }
}
