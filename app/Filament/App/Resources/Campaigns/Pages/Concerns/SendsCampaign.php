<?php

namespace App\Filament\App\Resources\Campaigns\Pages\Concerns;

use App\Filament\App\Resources\Campaigns\CampaignResource;
use App\Jobs\SendCampaign;
use App\Models\Campaign;
use App\Services\MessageSender;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/** Header actions shared by the create and edit pages: test send, send now, schedule. */
trait SendsCampaign
{
    /** Copy the SMS textarea (not a model attribute) into body. */
    protected function normaliseBody(array $data): array
    {
        if (($data['channel'] ?? null) === 'sms') {
            $data['body'] = (string) ($this->data['sms_body'] ?? $data['body'] ?? '');
            $data['subject'] = null;
        }

        return $data;
    }

    protected function campaignActions(): array
    {
        return [
            Action::make('test')
                ->label('Send test')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->visible(fn () => $this->record?->exists)
                ->schema(fn () => [TextInput::make('to')->label($this->record->channel === 'sms' ? 'Phone' : 'Email')->required()->default($this->record->channel === 'sms' ? auth()->user()->phone : auth()->user()->email)])
                ->action(function (array $data): void {
                    $this->save(false);
                    $campaign = $this->record->refresh();
                    $contact = \App\Models\Contact::upsertFor($campaign->subAccount, [$campaign->channel === 'sms' ? 'phone' : 'email' => $data['to'], 'first_name' => auth()->user()->name, 'source' => 'Campaign test']);
                    $message = MessageSender::send($contact, $campaign->channel, $campaign->body, '[Test] '.$campaign->subject, ['html' => $campaign->channel === 'email', 'footer' => true]);
                    Notification::make()->title($message->status === 'failed' ? 'Test failed: '.$message->error : 'Test sent to '.$data['to'])->{$message->status === 'failed' ? 'danger' : 'success'}()->send();
                }),
            Action::make('schedule')
                ->label('Schedule')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->visible(fn () => $this->record?->exists)
                ->schema([DateTimePicker::make('scheduled_at')->label('Send at')->seconds(false)->minDate(now())->required()->default(fn () => $this->record->scheduled_at ?? now()->addHour()->startOfHour())])
                ->action(function (array $data): void {
                    $this->save(false);
                    $this->record->update(['status' => 'scheduled', 'scheduled_at' => $data['scheduled_at']]);
                    Notification::make()->success()->title('Scheduled for '.$this->record->scheduled_at->format('M j, g:i A'))->send();
                    $this->redirect(CampaignResource::getUrl('index'));
                }),
            Action::make('sendNow')
                ->label('Send now')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $this->record?->exists)
                ->requiresConfirmation()
                ->modalDescription(fn () => 'This sends to '.number_format($this->record->audience()->count()).' contacts (minus anyone who opted out). It can\'t be undone.')
                ->action(function (): void {
                    $this->save(false);
                    /** @var Campaign $campaign */
                    $campaign = $this->record->refresh();
                    $campaign->update(['status' => 'sending']);
                    SendCampaign::dispatch($campaign);
                    Notification::make()->success()->title('Sending…')->body('Stats fill in as messages go out.')->send();
                    $this->redirect(CampaignResource::getUrl('view', ['record' => $campaign]));
                }),
        ];
    }
}
