<?php

namespace App\Filament\App\Resources\Payments;

use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Filament\App\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Transactions received (manual or Stripe). Payments are recorded from invoices. */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')->label('Date')->dateTime()->sortable(),
                TextColumn::make('contact.full_name')->label('Contact'),
                TextColumn::make('invoice.number')->label('Invoice')
                    ->url(fn (Payment $r) => $r->invoice ? InvoiceResource::getUrl('edit', ['record' => $r->invoice]) : null),
                TextColumn::make('method')->badge()->formatStateUsing(fn ($state) => Payment::METHODS[$state] ?? $state),
                TextColumn::make('reference')->limit(24)->placeholder('-'),
                TextColumn::make('amount')->money(fn (Payment $r) => $r->currency)->sortable()
                    ->summarize(Sum::make()->label('Total')),
            ])
            ->filters([
                SelectFilter::make('method')->options(Payment::METHODS),
                Filter::make('this_month')->label('This month')->query(fn (Builder $q) => $q->where('paid_at', '>=', now()->startOfMonth())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
