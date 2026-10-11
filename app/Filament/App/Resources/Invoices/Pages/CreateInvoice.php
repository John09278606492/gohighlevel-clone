<?php

namespace App\Filament\App\Resources\Invoices\Pages;

use App\Filament\App\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function afterCreate(): void
    {
        $this->record->recalculate();
        \App\Models\Activity::log($this->record->contact, 'invoice', "Invoice {$this->record->number} created", $this->record);
    }

    protected function getRedirectUrl(): string
    {
        return InvoiceResource::getUrl('edit', ['record' => $this->record]);
    }
}
