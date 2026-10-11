<?php

use App\Services\CampaignSender;
use App\Services\SocialPublisher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('campaigns:send-due', function () {
    $this->info(CampaignSender::sendDue().' scheduled campaign(s) sent.');
})->purpose('Send email/SMS campaigns whose scheduled time has passed');

Artisan::command('social:publish-due', function () {
    $this->info(SocialPublisher::publishDue().' social post(s) published.');
})->purpose('Publish scheduled social planner posts that are due');

// `php artisan schedule:work` (or a cron running `schedule:run` every minute) drives these.
Schedule::command('campaigns:send-due')->everyMinute()->withoutOverlapping();
Schedule::command('social:publish-due')->everyMinute()->withoutOverlapping();
