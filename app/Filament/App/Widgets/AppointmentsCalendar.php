<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Guava\Calendar\Contracts\ContextualInfo;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\Actions\CreateAction;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\ValueObjects\DateSelectInfo;
use Guava\Calendar\ValueObjects\EventDropInfo;
use Guava\Calendar\ValueObjects\EventResizeInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Appointments of the current sub-account on a drag-and-drop calendar (Guava Calendar). */
class AppointmentsCalendar extends CalendarWidget
{
    protected static bool $isDiscovered = false;

    protected CalendarViewType $calendarView = CalendarViewType::TimeGridWeek;

    protected bool $eventClickEnabled = true;

    protected bool $eventDragEnabled = true;

    protected bool $eventResizeEnabled = true;

    protected bool $dateClickEnabled = true;

    protected bool $dateSelectEnabled = true;

    protected bool $useFilamentTimezone = true;

    protected ?string $defaultEventClickAction = 'edit';

    public ?int $calendarId = null;

    public function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            'headerToolbar' => [
                'start' => 'prev,next today',
                'center' => 'title',
                'end' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'buttonText' => [
                'today' => 'Today',
                'dayGridMonth' => 'Month',
                'timeGridWeek' => 'Week',
                'timeGridDay' => 'Day',
                'listWeek' => 'List',
            ],
            'slotMinTime' => '07:00:00',
            'slotMaxTime' => '21:00:00',
            'nowIndicator' => true,
        ];
    }

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        return Appointment::query()
            ->with(['contact', 'calendar'])
            ->where('sub_account_id', Filament::getTenant()?->getKey())
            ->when($this->calendarId, fn ($q) => $q->where('calendar_id', $this->calendarId))
            ->where('ends_at', '>=', $info->start)
            ->where('starts_at', '<=', $info->end);
    }

    public function defaultSchema(Schema $schema): Schema
    {
        return AppointmentResource::form($schema);
    }

    public function createAppointmentAction(): CreateAction
    {
        return $this->createAction(Appointment::class)
            ->label('New appointment')
            ->mountUsing(function (Schema $form, ?ContextualInfo $info): void {
                $start = match (true) {
                    $info instanceof DateSelectInfo => $info->start,
                    $info instanceof DateClickInfo => $info->date,
                    default => now()->addHour()->startOfHour(),
                };
                $end = $info instanceof DateSelectInfo ? $info->end : $start->addMinutes(30);

                $form->fill([
                    'calendar_id' => $this->calendarId ?? \App\Models\Calendar::where('sub_account_id', Filament::getTenant()?->getKey())->value('id'),
                    'status' => 'booked',
                    'starts_at' => $start->format('Y-m-d H:i'),
                    'ends_at' => $end->format('Y-m-d H:i'),
                ]);
            })
            ->mutateDataUsing(fn (array $data) => $data + ['sub_account_id' => Filament::getTenant()->getKey()]);
    }

    protected function getDateClickContextMenuActions(): array
    {
        return [$this->createAppointmentAction()];
    }

    protected function getDateSelectContextMenuActions(): array
    {
        return [$this->createAppointmentAction()];
    }

    protected function onEventDrop(EventDropInfo $info, Model $event): bool
    {
        return $this->reschedule($event, $info->event->getStart(), $info->event->getEnd());
    }

    protected function onEventResize(EventResizeInfo $info, Model $event): bool
    {
        return $this->reschedule($event, $info->event->getStart(), $info->event->getEnd());
    }

    protected function reschedule(Model $event, $start, $end): bool
    {
        if (! $event instanceof Appointment || $event->sub_account_id !== Filament::getTenant()?->getKey()) {
            return false;
        }

        $event->update(['starts_at' => $start, 'ends_at' => $end]);

        return true;
    }

    /** Records opened from the calendar (view, edit, drag) are looked up inside the current sub-account only. */
    protected function getEloquentQuery(string $model): Builder
    {
        return $model::query()->where('sub_account_id', Filament::getTenant()?->getKey());
    }

    public function getHeaderActions(): array
    {
        return [$this->createAppointmentAction()];
    }
}
