<?php

namespace App\Filament\App\Resources\Invoices;

use App\Filament\App\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\App\Resources\Invoices\Pages\EditInvoice;
use App\Filament\App\Resources\Invoices\Pages\ListInvoices;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\Product;
use App\Services\MessageSender;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $overdue = Invoice::where('sub_account_id', Access::tenantId())
            ->whereIn('status', ['sent', 'partially_paid'])
            ->whereDate('due_date', '<', today())
            ->count();

        return $overdue ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        $currency = fn () => Filament::getTenant()?->currency ?? 'USD';

        return $schema->columns(3)->components([
            Section::make('Invoice')->columnSpan(2)->columns(2)->schema([
                Select::make('contact_id')->label('Bill to')
                    ->relationship('contact', 'email', modifyQueryUsing: fn ($query) => $query->where('sub_account_id', Access::tenantId()))
                    ->getOptionLabelFromRecordUsing(fn (Contact $r) => $r->full_name.($r->email ? " ({$r->email})" : ''))
                    ->searchable(['first_name', 'last_name', 'email', 'company'])
                    ->default(fn () => request()->integer('contact') ?: null)
                    ->required(),
                TextInput::make('title')->placeholder('Website redesign')->maxLength(150),
                DatePicker::make('issue_date')->default(now())->required(),
                DatePicker::make('due_date')->default(now()->addDays(14))->afterOrEqual('issue_date'),
                Select::make('opportunity_id')->label('Opportunity')
                    ->options(fn (Get $get) => Opportunity::where('sub_account_id', Access::tenantId())->when($get('contact_id'), fn ($q, $id) => $q->where('contact_id', $id))->pluck('name', 'id')),
            ]),
            Section::make('Totals')->columnSpan(1)->schema([
                TextInput::make('discount')->numeric()->minValue(0)->default(0)->prefix($currency)->live(onBlur: true),
                TextInput::make('tax_rate')->label('Tax')->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(fn () => Filament::getTenant()?->default_tax_rate ?? 0)->live(onBlur: true),
                TextEntry::make('preview_total')->label('Total')->size('lg')->weight('bold')
                    ->state(function (Get $get) use ($currency) {
                        $subtotal = collect($get('items') ?? [])->sum(fn ($i) => (float) ($i['quantity'] ?? 0) * (float) ($i['unit_price'] ?? 0));
                        $taxable = max(0, $subtotal - (float) $get('discount'));

                        return $currency().' '.number_format($taxable * (1 + (float) $get('tax_rate') / 100), 2);
                    }),
            ]),
            Section::make('Line items')->columnSpanFull()->schema([
                Repeater::make('items')
                    ->relationship()
                    ->hiddenLabel()
                    ->orderColumn('sort')
                    ->columns(12)
                    ->defaultItems(1)
                    ->live()
                    ->addActionLabel('Add line')
                    ->schema([
                        Select::make('product_id')->label('Product')->columnSpan(3)
                            ->options(fn () => Product::where('sub_account_id', Access::tenantId())->where('is_active', true)->pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($product = Product::find($state)) {
                                    $set('description', $product->name);
                                    $set('unit_price', $product->price);
                                }
                            }),
                        TextInput::make('description')->required()->columnSpan(5),
                        TextInput::make('quantity')->numeric()->default(1)->minValue(0.01)->required()->columnSpan(2)->live(onBlur: true),
                        TextInput::make('unit_price')->label('Price')->numeric()->default(0)->required()->columnSpan(2)->live(onBlur: true),
                    ]),
            ]),
            Textarea::make('notes')->rows(3)->columnSpanFull()->placeholder('Shown on the invoice'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->weight('medium')->searchable()->description(fn (Invoice $r) => $r->title),
                TextColumn::make('contact.full_name')->label('Contact')->searchable(['first_name', 'last_name', 'email']),
                TextColumn::make('total')->money(fn (Invoice $r) => $r->currency)->sortable(),
                TextColumn::make('balance')->label('Due')->state(fn (Invoice $r) => $r->balance())->money(fn (Invoice $r) => $r->currency),
                TextColumn::make('status')->badge()
                    ->state(fn (Invoice $r) => $r->isOverdue() ? 'overdue' : $r->status)
                    ->formatStateUsing(fn ($state) => $state === 'overdue' ? 'Overdue' : (Invoice::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => match ($state) { 'paid' => 'success', 'overdue', 'void' => 'danger', 'partially_paid' => 'warning', 'sent' => 'info', default => 'gray' }),
                TextColumn::make('due_date')->date()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(Invoice::STATUSES)])
            ->recordActions([
                ActionGroup::make([
                    static::sendAction(),
                    static::recordPaymentAction(),
                    Action::make('open')->label('Open client view')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Invoice $r) => $r->publicUrl(), true),
                    EditAction::make()->visible(fn (Invoice $r) => $r->status !== 'void'),
                    Action::make('void')->label('Void')->icon('heroicon-o-no-symbol')->color('danger')->requiresConfirmation()
                        ->visible(fn (Invoice $r) => ! in_array($r->status, ['void', 'paid'], true))
                        ->action(fn (Invoice $r) => $r->update(['status' => 'void'])),
                    DeleteAction::make()->visible(fn (Invoice $r) => $r->status === 'draft'),
                ]),
            ]);
    }

    public static function sendAction(): Action
    {
        return Action::make('send')
            ->label(fn (Invoice $r) => $r->sent_at ? 'Resend' : 'Send')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (Invoice $r) => ! in_array($r->status, ['void', 'paid'], true))
            ->schema([
                Select::make('channel')->options(['email' => 'Email', 'sms' => 'SMS (text-to-pay)'])->default('email')->required(),
            ])
            ->action(function (Invoice $record, array $data): void {
                $record->recalculate();
                $account = $record->subAccount;
                $money = $record->currency.' '.number_format($record->balance(), 2);
                $body = $data['channel'] === 'sms'
                    ? "Hi {{ contact.first_name }}, here is invoice {$record->number} from {$account->name} for {$money}: {$record->publicUrl()}"
                    : "<p>Hi {{ contact.first_name }},</p><p>Here is invoice <strong>{$record->number}</strong> from {$account->name} for <strong>{$money}</strong>".($record->due_date ? ', due '.$record->due_date->format('M j, Y') : '').".</p><p><a href=\"{$record->publicUrl()}\">View and pay the invoice</a></p><p>Thank you!</p>";

                $message = MessageSender::send($record->contact, $data['channel'], $body, "Invoice {$record->number} from {$account->name}", ['html' => $data['channel'] === 'email']);

                if ($message->status === 'failed') {
                    Notification::make()->danger()->title('Not sent')->body($message->error)->send();

                    return;
                }

                $record->forceFill(['status' => $record->status === 'draft' ? 'sent' : $record->status, 'sent_at' => now()])->save();
                \App\Models\Activity::log($record->contact, 'invoice', "Invoice {$record->number} sent ({$money})", $record);
                Notification::make()->success()->title("Invoice sent to {$record->contact->full_name}")->send();
            });
    }

    public static function recordPaymentAction(): Action
    {
        return Action::make('recordPayment')
            ->label('Record payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Invoice $r) => ! in_array($r->status, ['void', 'paid', 'draft'], true) && $r->balance() > 0)
            ->schema(fn (Invoice $record) => [
                TextInput::make('amount')->numeric()->minValue(0.01)->maxValue($record->balance())->default($record->balance())->prefix($record->currency)->required(),
                Select::make('method')->options(collect(Payment::METHODS)->except('stripe'))->default('bank_transfer')->required(),
                TextInput::make('reference')->placeholder('Check #, transfer reference…'),
                Textarea::make('notes')->rows(2),
            ])
            ->action(function (Invoice $record, array $data): void {
                $record->recordPayment((float) $data['amount'], $data['method'], $data['reference'] ?: null, $data['notes'] ?? null);
                Notification::make()->success()->title('Payment recorded')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
