<?php

namespace App\Livewire;

use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Contact;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use WooServ\FilamentDateTimeSlots\Forms\Components\DateTimeSlotPicker;

/** The public booking widget for one calendar (GoHighLevel's booking page). */
class BookingWidget extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Calendar $calendar;

    public ?array $data = [];

    public ?int $bookedAppointmentId = null;

    public function mount(Calendar $calendar): void
    {
        $this->calendar = $calendar;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $calendar = $this->calendar;

        return $schema
            ->statePath('data')
            ->components([
                DateTimeSlotPicker::make('slot')
                    ->label('Pick a time')
                    ->format('Y-m-d H:i')
                    ->timezone($calendar->timezone ?: 'UTC')
                    ->workingHours($calendar->workingHoursForPicker())
                    ->blockedSlots(fn () => $calendar->blockedSlots())
                    ->slotInterval($calendar->slot_interval_minutes)
                    ->minimumLeadTime($calendar->minimum_notice_minutes)
                    ->minDate(now())
                    ->maxDate(now()->addDays($calendar->booking_window_days))
                    ->required(),
                Grid::make(2)->schema([
                    TextInput::make('first_name')->required()->maxLength(100),
                    TextInput::make('last_name')->maxLength(100),
                    TextInput::make('email')->email()->required()->maxLength(255),
                    TextInput::make('phone')->tel()->maxLength(50),
                ]),
                Textarea::make('notes')->label('Anything we should know?')->rows(3)->maxLength(2000),
            ]);
    }

    public function book(): void
    {
        $data = $this->form->getState();
        $calendar = $this->calendar;

        $start = Carbon::parse($data['slot']);
        $end = $start->copy()->addMinutes($calendar->duration_minutes);

        $taken = $calendar->appointments()
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();

        if ($taken || $start->isPast()) {
            throw ValidationException::withMessages(['data.slot' => 'Sorry, that time was just taken. Please pick another.']);
        }

        $contact = Contact::upsertFor($calendar->subAccount, [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'source' => 'Booking: '.$calendar->name,
        ]);

        $appointment = Appointment::create([
            'sub_account_id' => $calendar->sub_account_id,
            'calendar_id' => $calendar->id,
            'contact_id' => $contact->id,
            'user_id' => $calendar->user_id,
            'title' => $calendar->name.' with '.$contact->full_name,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => 'booked',
            'source' => 'booking_page',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->bookedAppointmentId = $appointment->id;
    }

    public function getBookedAppointment(): ?Appointment
    {
        return $this->bookedAppointmentId ? Appointment::find($this->bookedAppointmentId) : null;
    }

    public function render()
    {
        return view('livewire.booking-widget');
    }
}
