<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GoHighLevel's "sub-account" (a.k.a. location): one client business the agency manages.
 * It is the Filament tenant of the /app panel.
 */
class SubAccount extends Model implements HasAvatar
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'chat_widget_enabled' => 'boolean',
            'twilio_token' => 'encrypted',
            'stripe_secret_key' => 'encrypted',
            'default_tax_rate' => 'decimal:2',
            'review_threshold' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SubAccount $account): void {
            $account->slug ??= static::uniqueSlug($account->name);
            $account->inbound_secret ??= Str::random(32);
        });

        // Every new sub-account starts with a default sales pipeline and a booking calendar.
        static::created(fn (SubAccount $account) => $account->provisionDefaults());
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'account';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function provisionDefaults(): void
    {
        if ($this->pipelines()->doesntExist()) {
            $pipeline = $this->pipelines()->create(['name' => 'Sales Pipeline']);

            foreach ([
                ['New Lead', 'gray'],
                ['Contacted', 'info'],
                ['Appointment Booked', 'primary'],
                ['Proposal Sent', 'warning'],
                ['Closed', 'success'],
            ] as $i => [$name, $color]) {
                $pipeline->stages()->create(['name' => $name, 'color' => $color, 'sort' => $i]);
            }
        }

        if ($this->calendars()->doesntExist()) {
            $this->calendars()->create([
                'name' => 'Discovery Call',
                'slug' => 'discovery-call',
                'description' => 'A 30 minute call to see how we can help.',
                'duration_minutes' => 30,
                'slot_interval_minutes' => 30,
                'timezone' => $this->timezone ?: 'UTC',
                'working_hours' => Calendar::defaultWorkingHours(),
            ]);
        }
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class)->orderBy('sort');
    }

    public function customValues(): HasMany
    {
        return $this->hasMany(CustomValue::class);
    }

    public function smartLists(): HasMany
    {
        return $this->hasMany(SmartList::class)->orderBy('sort');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function triggerLinks(): HasMany
    {
        return $this->hasMany(TriggerLink::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reviewRequests(): HasMany
    {
        return $this->hasMany(ReviewRequest::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function hasTwilio(): bool
    {
        return filled($this->twilio_sid) && filled($this->twilio_token) && filled($this->twilio_from);
    }

    public function hasStripe(): bool
    {
        return filled($this->stripe_secret_key);
    }

    public function chatWidgetSnippet(): string
    {
        return '<script src="'.route('widget.script', $this).'" async></script>';
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
