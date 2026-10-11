<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An email or SMS broadcast to a smart list and/or tagged contacts. */
class Campaign extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected $attributes = ['status' => 'draft'];

    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'sending' => 'Sending',
        'sent' => 'Sent',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return ['tags' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function smartList(): BelongsTo
    {
        return $this->belongsTo(SmartList::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /** The contacts this campaign is aimed at, before DND and channel checks. */
    public function audience(): Builder
    {
        $query = Contact::query()->where('sub_account_id', $this->sub_account_id);

        if ($this->smartList) {
            SmartList::apply($query, $this->smartList->criteria ?? []);
        }

        if (filled($this->tags)) {
            $query->withAnyTags($this->tags);
        }

        return $query;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    public function rate(string $metric): float
    {
        return $this->sent_count ? round($this->{$metric.'_count'} / $this->sent_count * 100, 1) : 0.0;
    }
}
