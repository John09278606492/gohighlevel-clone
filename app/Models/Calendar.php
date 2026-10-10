<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Calendar extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    public const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    protected function casts(): array
    {
        return ['working_hours' => 'array', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Calendar $calendar): void {
            $calendar->slug = filled($calendar->slug) ? Str::slug($calendar->slug) : Str::slug($calendar->name);
            $calendar->working_hours ??= static::defaultWorkingHours();
        });
    }

    public static function defaultWorkingHours(): array
    {
        return collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->map(fn ($day) => ['day' => $day, 'start' => '09:00', 'end' => '17:00'])
            ->all();
    }

    /** Working hours in the shape the Date Time Slots picker expects: ['monday' => ['09:00', '17:00']]. */
    public function workingHoursForPicker(): array
    {
        return collect($this->working_hours ?? [])
            ->filter(fn ($row) => filled($row['day'] ?? null) && filled($row['start'] ?? null) && filled($row['end'] ?? null))
            ->mapWithKeys(fn ($row) => [$row['day'] => [$row['start'], $row['end']]])
            ->all();
    }

    /**
     * Slots already taken by appointments, keyed by date, for the picker's blockedSlots().
     * A slot is blocked when a meeting starting there would overlap a booked appointment.
     */
    public function blockedSlots(): array
    {
        $tz = $this->timezone ?: 'UTC';
        $hours = $this->workingHoursForPicker();
        $interval = max(5, (int) $this->slot_interval_minutes);
        $blocked = [];

        $this->appointments()
            ->where('status', '!=', 'cancelled')
            ->where('ends_at', '>=', now())
            ->get(['starts_at', 'ends_at'])
            ->each(function (Appointment $appointment) use (&$blocked, $tz, $hours, $interval): void {
                $start = CarbonImmutable::parse($appointment->starts_at)->setTimezone($tz);
                $end = CarbonImmutable::parse($appointment->ends_at)->setTimezone($tz);
                [$open, $close] = $hours[strtolower($start->englishDayOfWeek)] ?? ['00:00', '23:59'];

                $slot = CarbonImmutable::parse($start->toDateString().' '.$open, $tz);
                $dayEnd = CarbonImmutable::parse($start->toDateString().' '.$close, $tz);

                for (; $slot->lt($dayEnd); $slot = $slot->addMinutes($interval)) {
                    if ($slot->lt($end) && $slot->addMinutes($this->duration_minutes)->gt($start)) {
                        $blocked[$slot->toDateString()][] = $slot->format('H:i');
                    }
                }
            });

        return array_map(fn ($slots) => array_values(array_unique($slots)), $blocked);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function bookingUrl(): string
    {
        return route('booking.show', [$this->subAccount, $this->slug]);
    }
}
