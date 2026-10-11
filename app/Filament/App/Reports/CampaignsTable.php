<?php

namespace App\Filament\App\Reports;

use App\Filament\App\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Support\Access;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CampaignsTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent campaigns';

    public function table(Table $table): Table
    {
        return $table
            ->query(Campaign::where('sub_account_id', Access::tenantId())->where('status', 'sent')->latest('sent_at'))
            ->paginated([5])
            ->recordUrl(fn (Campaign $r) => CampaignResource::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('channel')->badge(),
                TextColumn::make('sent_at')->dateTime(),
                TextColumn::make('sent_count')->label('Delivered'),
                TextColumn::make('open')->label('Open rate')->state(fn (Campaign $r) => $r->channel === 'email' ? $r->rate('opened').'%' : '-'),
                TextColumn::make('click')->label('Click rate')->state(fn (Campaign $r) => $r->channel === 'email' ? $r->rate('clicked').'%' : '-'),
                TextColumn::make('unsubscribed_count')->label('Unsubscribed'),
            ]);
    }
}
