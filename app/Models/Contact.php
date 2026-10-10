<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Packstub\Flow\Concerns\HasWorkflows;
use Spatie\Tags\HasTags;

class Contact extends Model
{
    use BelongsToSubAccount, HasTags, HasWorkflows;

    protected $guarded = [];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'dnd' => 'boolean',
            'custom_fields' => 'array',
            'date_of_birth' => 'date',
            'last_activity_at' => 'datetime',
        ];
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
