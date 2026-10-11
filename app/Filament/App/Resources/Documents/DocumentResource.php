<?php

namespace App\Filament\App\Resources\Documents;

use App\Filament\App\Resources\Documents\Pages\CreateDocument;
use App\Filament\App\Resources\Documents\Pages\EditDocument;
use App\Filament\App\Resources\Documents\Pages\ListDocuments;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Opportunity;
use App\Services\MessageSender;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Proposals, estimates and contracts with online e-signature. */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?string $navigationLabel = 'Documents & contracts';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->schema([
                TextInput::make('title')->required()->maxLength(200)->placeholder('Website proposal'),
                RichEditor::make('body')->required()->label('Content')
                    ->default('<h2>Scope</h2><p>Describe the work…</p><h2>Investment</h2><p>…</p><h2>Terms</h2><p>…</p>'),
            ]),
            Section::make()->columnSpan(1)->schema([
                Select::make('contact_id')->label('Recipient')
                    ->relationship('contact', 'email', modifyQueryUsing: fn ($query) => $query->where('sub_account_id', Access::tenantId()))
                    ->getOptionLabelFromRecordUsing(fn (Contact $r) => $r->full_name)
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->default(fn () => request()->integer('contact') ?: null)
                    ->required()
                    ->live(),
                Select::make('opportunity_id')->label('Opportunity (marked won when signed)')
                    ->options(fn (Get $get) => Opportunity::where('sub_account_id', Access::tenantId())->when($get('contact_id'), fn ($q, $id) => $q->where('contact_id', $id))->pluck('name', 'id')),
                TextInput::make('amount')->numeric()->minValue(0)->prefix(fn () => Filament::getTenant()?->currency),
                DatePicker::make('expires_on')->minDate(today()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->weight('medium')->searchable(),
                TextColumn::make('contact.full_name')->label('Recipient'),
                TextColumn::make('amount')->money(fn () => Filament::getTenant()?->currency ?? 'USD')->placeholder('-'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Document::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'signed' => 'success', 'viewed' => 'info', 'sent' => 'warning', 'declined' => 'danger', default => 'gray' }),
                TextColumn::make('signed_at')->dateTime()->placeholder('-')->description(fn (Document $r) => $r->signer_name),
            ])
            ->filters([SelectFilter::make('status')->options(Document::STATUSES)])
            ->recordActions([
                ActionGroup::make([
                    static::sendAction(),
                    Action::make('open')->label('Open client view')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Document $r) => $r->publicUrl(), true),
                    EditAction::make()->visible(fn (Document $r) => $r->status !== 'signed'),
                    ReplicateAction::make()->label('Duplicate')
                        ->beforeReplicaSaved(fn (Document $replica) => $replica->fill(['status' => 'draft', 'token' => null, 'signer_name' => null, 'signer_ip' => null, 'signed_at' => null, 'sent_at' => null, 'viewed_at' => null])),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function sendAction(): Action
    {
        return Action::make('send')
            ->label(fn (Document $r) => $r->sent_at ? 'Resend' : 'Send for signature')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (Document $r) => in_array($r->status, ['draft', 'sent', 'viewed'], true))
            ->schema([Select::make('channel')->options(['email' => 'Email', 'sms' => 'SMS'])->default('email')->required()])
            ->action(function (Document $record, array $data): void {
                $account = $record->subAccount;
                $body = $data['channel'] === 'sms'
                    ? "Hi {{ contact.first_name }}, {$account->name} sent you \"{$record->title}\" to review and sign: {$record->publicUrl()}"
                    : "<p>Hi {{ contact.first_name }},</p><p>{$account->name} sent you <strong>".e($record->title).'</strong> to review and sign.</p><p><a href="'.$record->publicUrl().'">Review and sign</a></p>';

                $message = MessageSender::send($record->contact, $data['channel'], $body, $record->title.' from '.$account->name, ['html' => $data['channel'] === 'email']);

                if ($message->status === 'failed') {
                    Notification::make()->danger()->title('Not sent')->body($message->error)->send();

                    return;
                }

                $record->update(['status' => $record->status === 'draft' ? 'sent' : $record->status, 'sent_at' => now()]);
                Activity::log($record->contact, 'document', "Sent \"{$record->title}\" for signature", $record);
                Notification::make()->success()->title('Sent for signature')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
