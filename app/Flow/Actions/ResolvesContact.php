<?php

namespace App\Flow\Actions;

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\SubAccount;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/** Helpers shared by the CRM workflow actions. */
trait ResolvesContact
{
    /** The contact a run is about: the record itself, or the contact of an opportunity or appointment. */
    protected function contactFrom(array $payload): ?Contact
    {
        $model = $payload['model'] ?? null;

        return match (true) {
            $model instanceof Contact => $model,
            $model instanceof Opportunity, $model instanceof Appointment => $model->contact,
            default => null,
        };
    }

    protected function subAccountIdFrom(array $payload): ?int
    {
        $model = $payload['model'] ?? null;

        if ($model instanceof Model && $model->getAttribute('sub_account_id')) {
            return (int) $model->getAttribute('sub_account_id');
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof SubAccount ? $tenant->getKey() : null;
    }

    /** The current sub-account while the workflow is being edited, for scoping select options. */
    protected static function editingSubAccountId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof SubAccount ? $tenant->getKey() : null;
    }
}
