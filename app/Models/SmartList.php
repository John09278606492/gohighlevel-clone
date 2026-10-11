<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved contact segment. The criteria are plain data so they can be shown as
 * tabs on the contacts list, used as a campaign audience, or copied in a snapshot.
 */
class SmartList extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'is_shared' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contacts(): Builder
    {
        return static::apply(Contact::query()->where('sub_account_id', $this->sub_account_id), $this->criteria ?? []);
    }

    /** Narrow a contacts query by smart-list criteria. */
    public static function apply(Builder $query, array $c): Builder
    {
        return $query
            ->when($c['tags_any'] ?? null, fn (Builder $q, $tags) => $q->withAnyTags((array) $tags))
            ->when($c['tags_all'] ?? null, fn (Builder $q, $tags) => $q->withAllTags((array) $tags))
            ->when($c['tags_none'] ?? null, fn (Builder $q, $tags) => $q->withoutTags((array) $tags))
            ->when($c['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
            ->when($c['source'] ?? null, fn (Builder $q, $source) => $q->where('source', 'like', "%{$source}%"))
            ->when($c['owner_id'] ?? null, fn (Builder $q, $owner) => $q->where('owner_id', $owner))
            ->when(($c['has_email'] ?? null) !== null && ($c['has_email'] ?? '') !== '', fn (Builder $q) => (bool) $c['has_email'] ? $q->whereNotNull('email')->where('email', '!=', '') : $q->where(fn ($q) => $q->whereNull('email')->orWhere('email', '')))
            ->when(($c['has_phone'] ?? null) !== null && ($c['has_phone'] ?? '') !== '', fn (Builder $q) => (bool) $c['has_phone'] ? $q->whereNotNull('phone')->where('phone', '!=', '') : $q->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', '')))
            ->when($c['created_within_days'] ?? null, fn (Builder $q, $days) => $q->where('created_at', '>=', now()->subDays((int) $days)))
            ->when($c['inactive_for_days'] ?? null, fn (Builder $q, $days) => $q->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', now()->subDays((int) $days))))
            ->when($c['has_open_opportunity'] ?? null, fn (Builder $q) => $q->whereHas('opportunities', fn ($q) => $q->where('status', 'open')))
            ->when($c['has_upcoming_appointment'] ?? null, fn (Builder $q) => $q->whereHas('appointments', fn ($q) => $q->where('starts_at', '>=', now())->whereNotIn('status', ['cancelled'])))
            ->when($c['exclude_dnd'] ?? null, fn (Builder $q) => $q->where('dnd', false))
            ->when($c['custom_field'] ?? null, function (Builder $q, $key) use ($c) {
                $value = $c['custom_value'] ?? null;

                return filled($value) ? $q->where("custom_fields->{$key}", $value) : $q->whereNotNull("custom_fields->{$key}");
            });
    }
}
