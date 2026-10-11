<?php

namespace App\Filament\App\Resources\Campaigns;

use App\Filament\App\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\App\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\App\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\App\Resources\Campaigns\Pages\ViewCampaign;
use App\Models\Campaign;
use App\Models\SmartList;
use App\Services\MergeTags;
use App\Support\Access;
use App\Support\TenantTags;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Email & SMS campaigns';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Message')->columnSpan(2)->schema([
                TextInput::make('name')->required()->maxLength(150)->placeholder('Spring promo'),
                Select::make('channel')->options(['email' => 'Email', 'sms' => 'SMS'])->default('email')->required()->live(),
                TextInput::make('subject')->required(fn (Get $get) => $get('channel') === 'email')->visible(fn (Get $get) => $get('channel') === 'email')->maxLength(200),
                RichEditor::make('body')
                    ->label('Email')
                    ->visible(fn (Get $get) => $get('channel') === 'email')
                    ->required(fn (Get $get) => $get('channel') === 'email')
                    ->toolbarButtons([['bold', 'italic', 'underline', 'link'], ['h2', 'h3', 'bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']]),
                Textarea::make('sms_body')
                    ->label('Text message')
                    ->rows(5)
                    ->maxLength(1000)
                    ->visible(fn (Get $get) => $get('channel') === 'sms')
                    ->required(fn (Get $get) => $get('channel') === 'sms')
                    ->helperText('"Reply STOP to opt out." is added automatically.')
                    ->afterStateHydrated(fn ($component, ?Campaign $record) => $record?->channel === 'sms' ? $component->state($record->body) : null)
                    ->dehydrated(false),
                TextEntry::make('merge_tags')->label('Merge tags')
                    ->state(new HtmlString(collect(MergeTags::AVAILABLE)->map(fn ($label, $tag) => '<code>'.e($tag).'</code> '.e($label))->implode('<br>'))),
            ]),
            Section::make('Audience')->columnSpan(1)->schema([
                Select::make('smart_list_id')->label('Smart list')
                    ->options(fn () => SmartList::where('sub_account_id', Access::tenantId())->pluck('name', 'id'))
                    ->placeholder('All contacts'),
                Select::make('tags')->label('…with any of these tags')->multiple()->options(fn () => TenantTags::options()),
                TextEntry::make('audience_size')->label('Matching contacts')
                    ->state(function (Get $get) {
                        $campaign = new Campaign(['sub_account_id' => Access::tenantId(), 'smart_list_id' => $get('smart_list_id'), 'tags' => $get('tags')]);

                        return number_format($campaign->audience()->count()).' (opt-outs are skipped when sending)';
                    }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->weight('medium')->searchable()->description(fn (Campaign $r) => $r->subject),
                TextColumn::make('channel')->badge()->formatStateUsing(fn ($state) => strtoupper($state)),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Campaign::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'sent' => 'success', 'scheduled' => 'warning', 'sending' => 'info', 'cancelled' => 'danger', default => 'gray' }),
                TextColumn::make('sent_count')->label('Sent')->numeric(),
                TextColumn::make('open_rate')->label('Opened')->state(fn (Campaign $r) => $r->channel === 'email' && $r->sent_count ? $r->rate('opened').'%' : '-'),
                TextColumn::make('click_rate')->label('Clicked')->state(fn (Campaign $r) => $r->channel === 'email' && $r->sent_count ? $r->rate('clicked').'%' : '-'),
                TextColumn::make('scheduled_at')->label('Scheduled / sent')->state(fn (Campaign $r) => $r->sent_at ?? $r->scheduled_at)->dateTime()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Campaign::STATUSES),
                SelectFilter::make('channel')->options(['email' => 'Email', 'sms' => 'SMS']),
            ])
            ->recordUrl(fn (Campaign $r) => $r->isEditable() ? static::getUrl('edit', ['record' => $r]) : static::getUrl('view', ['record' => $r]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Campaign $r) => $r->isEditable()),
                DeleteAction::make()->visible(fn (Campaign $r) => $r->status !== 'sending'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'view' => ViewCampaign::route('/{record}'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }
}
