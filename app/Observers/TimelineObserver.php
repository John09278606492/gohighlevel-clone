<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\ContactNote;
use App\Models\Opportunity;
use App\Support\CrmEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Writes the contact activity timeline and fires status-change CRM events for core records. */
class TimelineObserver
{
    public function created(Model $model): void
    {
        match (true) {
            $model instanceof ContactNote => Activity::log($model->contact, 'note', Str::limit($model->body, 120), $model),
            $model instanceof Appointment => Activity::log($model->contact, 'appointment', "Booked \"{$model->title}\" for ".$model->starts_at?->format('M j, Y g:i A'), $model),
            $model instanceof Opportunity => Activity::log($model->contact, 'opportunity', "Opportunity \"{$model->name}\" created in ".($model->stage?->name ?? 'pipeline'), $model),
            default => null,
        };
    }

    public function updated(Model $model): void
    {
        if ($model instanceof Appointment && $model->wasChanged('status')) {
            $label = Appointment::STATUSES[$model->status] ?? $model->status;
            Activity::log($model->contact, 'appointment', "Appointment \"{$model->title}\" marked {$label}", $model);
            CrmEvents::fire('appointment_status', $model->contact, $model, ['status' => $model->status, 'title' => $model->title]);
        }

        if ($model instanceof Appointment && $model->wasChanged('starts_at') && ! $model->wasChanged('status')) {
            Activity::log($model->contact, 'appointment', "Appointment \"{$model->title}\" rescheduled to ".$model->starts_at?->format('M j, Y g:i A'), $model);
        }

        if ($model instanceof Opportunity && $model->wasChanged('status')) {
            $label = Opportunity::STATUSES[$model->status] ?? $model->status;
            Activity::log($model->contact, 'opportunity', "Opportunity \"{$model->name}\" marked {$label}", $model, ['value' => $model->value]);
            CrmEvents::fire('opportunity_status', $model->contact, $model, ['status' => $model->status, 'name' => $model->name, 'value' => $model->value]);
        }

        if ($model instanceof Opportunity && $model->wasChanged('pipeline_stage_id')) {
            $stage = $model->stage()->value('name');
            Activity::log($model->contact, 'opportunity', "Opportunity \"{$model->name}\" moved to {$stage}", $model);
            CrmEvents::fire('opportunity_stage', $model->contact, $model, ['stage' => $stage, 'name' => $model->name]);
        }
    }
}
