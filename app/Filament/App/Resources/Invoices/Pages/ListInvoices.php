<?php

namespace App\Filament\App\Resources\Invoices\Pages;

use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\Access;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New invoice')];
    }

    public function getTabs(): array
    {
        $count = fn (callable $scope) => $scope(Invoice::where('sub_account_id', Access::tenantId()))->count();
        $overdue = fn ($query) => $query->whereIn('status', ['sent', 'partially_paid'])->whereDate('due_date', '<', today());

        return [
            'all' => Tab::make('All'),
            'outstanding' => Tab::make('Outstanding')->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['sent', 'partially_paid'])),
            'overdue' => Tab::make('Overdue')->modifyQueryUsing($overdue)->badge($count($overdue) ?: null)->badgeColor('danger'),
            'paid' => Tab::make('Paid')->modifyQueryUsing(fn ($query) => $query->where('status', 'paid')),
            'draft' => Tab::make('Drafts')->modifyQueryUsing(fn ($query) => $query->where('status', 'draft')),
        ];
    }
}
