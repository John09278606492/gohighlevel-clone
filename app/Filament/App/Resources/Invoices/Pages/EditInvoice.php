<?php

namespace App\Filament\App\Resources\Invoices\Pages;

use App\Filament\App\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getSubheading(): ?string
    {
        $r = $this->record;

        return \App\Models\Invoice::STATUSES[$r->status].' · paid '.$r->currency.' '.number_format((float) $r->amount_paid, 2).' of '.number_format((float) $r->total, 2);
    }

    protected function afterSave(): void
    {
        $this->record->recalculate();
    }

    protected function getHeaderActions(): array
    {
        return [
            InvoiceResource::sendAction()->record($this->record)->after(fn () => $this->refreshFormData(['status'])),
            InvoiceResource::recordPaymentAction()->record($this->record),
            Action::make('open')->label('Client view')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')->url(fn () => $this->record->publicUrl(), true),
            DeleteAction::make()->visible(fn () => $this->record->status === 'draft'),
        ];
    }
}
