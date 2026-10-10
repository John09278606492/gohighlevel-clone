<div class="ghl-booking-widget">
    @if ($appointment = $this->getBookedAppointment())
        <div class="ghl-booked">
            <div class="ghl-booked__icon">✓</div>
            <h2>You're booked!</h2>
            <p>
                {{ $calendar->name }}<br>
                <strong>{{ $appointment->starts_at->setTimezone($calendar->timezone)->format('l, F j, Y \a\t g:i A') }}</strong>
                ({{ $calendar->timezone }})
            </p>
            <p>{{ $calendar->confirmation_message ?: 'A confirmation has been sent to your email.' }}</p>
        </div>
    @else
        <form wire:submit="book">
            {{ $this->form }}

            <div class="ghl-booking-widget__submit">
                <x-filament::button type="submit" size="lg" wire:loading.attr="disabled">
                    Confirm booking
                </x-filament::button>
            </div>
        </form>
    @endif

    <x-filament-actions::modals />
</div>
