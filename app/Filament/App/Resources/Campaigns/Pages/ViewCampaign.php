<?php

namespace App\Filament\App\Resources\Campaigns\Pages;

use App\Filament\App\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/** Campaign report: delivery, opens, clicks, unsubscribes and the recipient list. */
class ViewCampaign extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CampaignResource::class;

    protected string $view = 'filament.app.campaigns.view';

    public function infolist(Schema $schema): Schema
    {
        $stat = fn (string $label, $state, mixed $help = null) => TextEntry::make(str($label)->slug('_'))->label($label)->state($state)->size('lg')->weight('bold')->helperText($help);

        return $schema->components([
            Grid::make(6)->schema([
                $stat('Status', fn (Campaign $r) => Campaign::STATUSES[$r->status] ?? $r->status),
                $stat('Recipients', fn (Campaign $r) => number_format($r->recipients_count)),
                $stat('Sent', fn (Campaign $r) => number_format($r->sent_count), fn (Campaign $r) => $r->skipped_count ? "{$r->skipped_count} skipped (opted out)" : null),
                $stat('Failed', fn (Campaign $r) => number_format($r->failed_count)),
                $stat('Opened', fn (Campaign $r) => $r->channel === 'email' ? $r->rate('opened').'%' : 'n/a', fn (Campaign $r) => $r->channel === 'email' ? "{$r->opened_count} people" : null),
                $stat('Clicked', fn (Campaign $r) => $r->channel === 'email' ? $r->rate('clicked').'%' : 'n/a', fn (Campaign $r) => "{$r->unsubscribed_count} unsubscribed"),
            ]),
            Section::make('Message')->collapsed()->schema([
                TextEntry::make('subject')->visible(fn (Campaign $r) => $r->channel === 'email'),
                TextEntry::make('body')->hiddenLabel()->state(fn (Campaign $r) => new HtmlString($r->channel === 'email' ? str($r->body)->sanitizeHtml() : nl2br(e($r->body)))),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(CampaignRecipient::query()->where('campaign_id', $this->record->id)->with('contact'))
            ->poll(fn () => $this->record->fresh()->status === 'sending' ? '3s' : null)
            ->heading('Recipients')
            ->columns([
                TextColumn::make('contact.full_name')->label('Contact')->searchable(['first_name', 'last_name', 'email']),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'sent' => 'success', 'failed' => 'danger', 'skipped' => 'gray', default => 'warning' })
                    ->description(fn (CampaignRecipient $r) => $r->error),
                TextColumn::make('sent_at')->dateTime()->placeholder('-'),
                TextColumn::make('opened_at')->dateTime()->placeholder('-'),
                TextColumn::make('clicked_at')->dateTime()->placeholder('-'),
            ])
            ->filters([SelectFilter::make('status')->options(['sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped', 'pending' => 'Pending'])]);
    }
}
