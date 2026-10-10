<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use CarlJanzell\FilamentPageBuilder\Concerns\HasBlocks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Page extends Model
{
    use BelongsToSubAccount, HasBlocks;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            $page->slug = Str::slug($page->slug ?: $page->title);
        });
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function publicUrl(): string
    {
        return route('pages.show', [$this->subAccount, $this->slug]);
    }

    /** The next step in the funnel, used by "next step" buttons. */
    public function nextStep(): ?self
    {
        if (! $this->funnel_id) {
            return null;
        }

        return static::where('funnel_id', $this->funnel_id)
            ->where('step_order', '>', $this->step_order)
            ->where('is_published', true)
            ->orderBy('step_order')
            ->first();
    }
}
