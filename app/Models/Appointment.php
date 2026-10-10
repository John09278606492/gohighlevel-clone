<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Packstub\Flow\Concerns\HasWorkflows;

class Appointment extends Model implements Eventable
{
    use BelongsToSubAccount, HasWorkflows;

    protected $guarded = [];

    public const STATUSES = [
        'booked' => 'Booked',
        'confirmed' => 'Confirmed',
        'showed' => 'Showed',
        'no_show' => 'No show',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            if ($appointment->calendar_id && blank($appointment->ends_at) && $appointment->starts_at) {
                $appointment->ends_at = $appointment->starts_at->copy()->addMinutes($appointment->calendar->duration_minutes);
            }
            if ($appointment->calendar_id && blank($appointment->sub_account_id)) {
                $appointment->sub_account_id = $appointment->calendar->sub_account_id;
            }
        });
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toCalendarEvent(): CalendarEvent
    {
        $color = match ($this->status) {
            'cancelled' => '#9ca3af',
            'no_show' => '#ef4444',
            'showed' => '#22c55e',
            default => $this->calendar?->color ?? '#6366f1',
        };

        return CalendarEvent::make($this)
            ->title(trim($this->title.($this->contact ? ' · '.$this->contact->full_name : '')))
            ->start($this->starts_at)
            ->end($this->ends_at)
            ->backgroundColor($color)
            ->textColor('#ffffff');
    }
}
