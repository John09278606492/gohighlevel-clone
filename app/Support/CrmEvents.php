<?php

namespace App\Support;

use App\Flow\Triggers\CrmEvent;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Packstub\Flow\Facades\Flow;

/**
 * Fires GoHighLevel-style automation triggers ("Tag added", "Invoice paid",
 * "Customer replied"...) into the workflow engine. Workflows pick them up with the
 * "CRM event" trigger; {{ model.* }} is the contact and {{ data.* }} the event data.
 */
class CrmEvents
{
    public const EVENTS = [
        'tag_added' => 'Contact tag added',
        'tag_removed' => 'Contact tag removed',
        'inbound_message' => 'Customer replied (email, SMS or chat)',
        'form_submitted' => 'Form submitted',
        'appointment_status' => 'Appointment status changed',
        'opportunity_status' => 'Opportunity status changed',
        'opportunity_stage' => 'Opportunity moved to a stage',
        'trigger_link_clicked' => 'Trigger link clicked',
        'email_opened' => 'Campaign email opened',
        'invoice_paid' => 'Invoice paid',
        'payment_received' => 'Payment received',
        'document_signed' => 'Document signed',
        'review_received' => 'Review received',
        'course_enrolled' => 'Enrolled in a course',
        'course_completed' => 'Course completed',
        'contact_unsubscribed' => 'Contact unsubscribed',
    ];

    public static function fire(string $event, ?Contact $contact, ?Model $subject = null, array $data = []): void
    {
        if (! $contact) {
            return;
        }

        try {
            Flow::dispatch(CrmEvent::class, [
                'model' => $contact,
                'event' => $event,
                'subject' => $subject,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            // An automation failing must never break the action that triggered it.
            report($e);
        }
    }
}
