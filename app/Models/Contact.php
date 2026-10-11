<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Packstub\Flow\Concerns\HasWorkflows;
use Spatie\Tags\HasTags;

class Contact extends Model
{
    use BelongsToSubAccount, HasWorkflows;
    use HasTags {
        attachTags as protected spatieAttachTags;
        detachTags as protected spatieDetachTags;
    }

    protected $guarded = [];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'dnd' => 'boolean',
            'dnd_email' => 'boolean',
            'dnd_sms' => 'boolean',
            'custom_fields' => 'array',
            'date_of_birth' => 'date',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Contact $contact) => $contact->unsubscribe_token ??= Str::random(40));
        static::created(fn (Contact $contact) => Activity::log($contact, 'contact_created', 'Contact created'.($contact->source ? " from {$contact->source}" : '')));
    }

    /** Attach tags and fire a "Tag added" workflow event for each tag that is new on the contact. */
    public function attachTags(array|\ArrayAccess|\Spatie\Tags\Tag|string $tags, ?string $type = null): static
    {
        $before = $this->exists ? $this->tags()->pluck('name')->map(fn ($n) => is_array($n) ? reset($n) : $n)->all() : [];
        $this->spatieAttachTags(is_string($tags) ? [$tags] : $tags, $type);

        if ($this->exists) {
            $added = array_values(array_diff(
                $this->tags()->pluck('name')->map(fn ($n) => is_array($n) ? reset($n) : $n)->all(),
                $before,
            ));

            if ($added) {
                Activity::log($this, 'tag', 'Tagged: '.implode(', ', $added));
                foreach ($added as $tag) {
                    \App\Support\CrmEvents::fire('tag_added', $this, null, ['tag' => $tag]);
                }
            }
        }

        return $this;
    }

    public function detachTags(array|\ArrayAccess $tags, ?string $type = null): static
    {
        $this->spatieDetachTags($tags, $type);

        foreach ((array) (is_array($tags) ? $tags : iterator_to_array($tags)) as $tag) {
            $name = $tag instanceof \Spatie\Tags\Tag ? $tag->name : $tag;
            \App\Support\CrmEvents::fire('tag_removed', $this, null, ['tag' => $name]);
        }

        return $this;
    }

    /** Names of the contact's tags as plain strings. */
    public function tagNames(): array
    {
        return $this->tags->pluck('name')->map(fn ($n) => is_array($n) ? reset($n) : $n)->values()->all();
    }

    /** Token used in tracked links and unsubscribe links; never exposes the contact id. */
    public function trackingToken(): string
    {
        if (! $this->unsubscribe_token) {
            $this->forceFill(['unsubscribe_token' => Str::random(40)])->saveQuietly();
        }

        return $this->unsubscribe_token;
    }

    public static function findByTrackingToken(?string $token): ?self
    {
        return $token ? static::where('unsubscribe_token', $token)->first() : null;
    }

    public function unsubscribeUrl(): string
    {
        return route('unsubscribe', $this->trackingToken());
    }

    /** Whether the contact accepts messages on this channel. */
    public function canReceive(string $channel): bool
    {
        return ! $this->dnd && match ($channel) {
            'email' => ! $this->dnd_email && filled($this->email),
            'sms' => ! $this->dnd_sms && filled($this->phone),
            default => true,
        };
    }

    public function getFirstNameOrFallbackAttribute(): string
    {
        return $this->first_name ?: 'there';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: ($this->email ?? $this->phone ?? 'Unnamed contact');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ContactNote::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /** Find a contact in a sub-account by email or phone, or create one. */
    public static function upsertFor(SubAccount $account, array $attributes): self
    {
        $attributes = array_filter($attributes, fn ($v) => filled($v));

        $existing = static::query()
            ->where('sub_account_id', $account->id)
            ->where(function ($q) use ($attributes) {
                $q->when($attributes['email'] ?? null, fn ($q, $email) => $q->orWhere('email', $email))
                    ->when($attributes['phone'] ?? null, fn ($q, $phone) => $q->orWhere('phone', $phone));
            })
            ->when(empty($attributes['email']) && empty($attributes['phone']), fn ($q) => $q->whereRaw('1 = 0'))
            ->first();

        if ($existing) {
            $existing->fill(array_diff_key($attributes, array_flip(['source'])))->fill(['last_activity_at' => now()])->save();

            return $existing;
        }

        return static::create($attributes + ['sub_account_id' => $account->id, 'last_activity_at' => now()]);
    }

    public function conversationOrCreate(): Conversation
    {
        return Conversation::firstOrCreate(
            ['sub_account_id' => $this->sub_account_id, 'contact_id' => $this->id],
        );
    }
}
