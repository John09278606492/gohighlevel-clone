<?php

namespace App\Forms;

use App\Models\Activity;
use App\Models\Contact;
use App\Support\CrmEvents;
use App\Models\SubAccount;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Contracts\SubmissionSink;
use Packstub\FormBuilder\Models\FormSubmission;

/**
 * Like GoHighLevel forms: every submission creates or updates a contact in the
 * form's sub-account (matched by email or phone), tags it with the form name and
 * adds the answers as a note. Creating the contact fires any "record created" workflows.
 */
class CreateContactFromSubmission implements SubmissionSink
{
    public function handle(FormSubmission $submission): void
    {
        $form = $submission->form;
        $account = SubAccount::find($form?->tenant_id);

        if (! $account) {
            return;
        }

        $attributes = ['source' => 'Form: '.$form->name];

        foreach ((array) $submission->data as $key => $value) {
            if (! is_scalar($value) || blank($value)) {
                continue;
            }

            $type = (string) data_get($submission->fields, "{$key}.type", '');
            $name = Str::of($key)->lower()->replace(['-', ' '], '_')->toString();

            match (true) {
                $type === 'email' || $name === 'email' => $attributes['email'] ??= (string) $value,
                $type === 'phone' || in_array($name, ['phone', 'mobile', 'phone_number'], true) => $attributes['phone'] ??= (string) $value,
                in_array($name, ['first_name', 'firstname'], true) => $attributes['first_name'] = (string) $value,
                in_array($name, ['last_name', 'lastname', 'surname'], true) => $attributes['last_name'] = (string) $value,
                in_array($name, ['company', 'company_name', 'business'], true) => $attributes['company'] = (string) $value,
                in_array($name, ['name', 'full_name', 'your_name'], true) => $this->splitName((string) $value, $attributes),
                default => null,
            };
        }

        if (empty($attributes['email']) && empty($attributes['phone'])) {
            return;
        }

        $contact = Contact::upsertFor($account, $attributes);
        $contact->attachTag(Str::limit($form->name, 50, ''));

        $lines = collect($submission->formatted())
            ->map(fn (array $row) => "{$row['label']}: {$row['value']}")
            ->implode("\n");

        $contact->notes()->create(['body' => "Submitted form \"{$form->name}\"\n\n{$lines}"]);

        Activity::log($contact, 'form', "Submitted form \"{$form->name}\"", $submission);
        CrmEvents::fire('form_submitted', $contact, $submission, ['form' => $form->name, 'answers' => $submission->data]);
    }

    private function splitName(string $name, array &$attributes): void
    {
        [$first, $last] = array_pad(explode(' ', trim($name), 2), 2, null);
        $attributes['first_name'] ??= $first;
        $attributes['last_name'] ??= $last;
    }
}
