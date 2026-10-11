<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One entry on a contact's activity timeline. */
class Activity extends Model
{
    use BelongsToSubAccount;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public const TYPES = [
        'contact_created' => ['Contact created', 'heroicon-o-user-plus', 'gray'],
        'note' => ['Note', 'heroicon-o-pencil-square', 'gray'],
        'message_in' => ['Message received', 'heroicon-o-inbox-arrow-down', 'info'],
        'message_out' => ['Message sent', 'heroicon-o-paper-airplane', 'primary'],
        'appointment' => ['Appointment', 'heroicon-o-calendar-days', 'warning'],
        'opportunity' => ['Opportunity', 'heroicon-o-currency-dollar', 'success'],
        'form' => ['Form submitted', 'heroicon-o-clipboard-document-check', 'info'],
        'campaign' => ['Campaign', 'heroicon-o-megaphone', 'primary'],
        'link_click' => ['Link clicked', 'heroicon-o-cursor-arrow-rays', 'info'],
        'invoice' => ['Invoice', 'heroicon-o-document-text', 'warning'],
        'payment' => ['Payment', 'heroicon-o-banknotes', 'success'],
        'review' => ['Review', 'heroicon-o-star', 'warning'],
        'document' => ['Document', 'heroicon-o-document-check', 'info'],
        'course' => ['Course', 'heroicon-o-academic-cap', 'primary'],
        'tag' => ['Tags', 'heroicon-o-tag', 'gray'],
        'dnd' => ['Subscription', 'heroicon-o-no-symbol', 'danger'],
    ];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    /** Record something that happened to a contact. Never throws: the timeline must not break the action it logs. */
    public static function log(?Contact $contact, string $type, string $description, ?Model $subject = null, array $properties = []): ?self
    {
        if (! $contact) {
            return null;
        }

        try {
            return static::create([
                'sub_account_id' => $contact->sub_account_id,
                'contact_id' => $contact->id,
                'user_id' => auth()->id(),
                'type' => $type,
                'description' => $description,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'properties' => $properties ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
