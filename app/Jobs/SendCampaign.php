<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\CampaignSender;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCampaign implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(public Campaign $campaign) {}

    public function uniqueId(): string
    {
        return (string) $this->campaign->id;
    }

    public function handle(): void
    {
        CampaignSender::send($this->campaign);
    }
}
