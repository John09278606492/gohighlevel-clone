<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use Illuminate\Support\Facades\URL;

/**
 * Sends a campaign: snapshot the audience into campaign_recipients (so the list
 * can't shift mid-send and nobody gets it twice), then deliver to each pending
 * recipient, honouring DND and per-channel opt-outs.
 */
class CampaignSender
{
    public static function send(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled', 'sending'], true)) {
            return $campaign;
        }

        $campaign->update(['status' => 'sending']);

        if ($campaign->recipients()->doesntExist()) {
            $campaign->audience()->select('contacts.id')->orderBy('contacts.id')->chunkById(500, function ($contacts) use ($campaign) {
                $rows = $contacts->map(fn ($c) => [
                    'campaign_id' => $campaign->id,
                    'contact_id' => $c->id,
                    'token' => \Illuminate\Support\Str::random(40),
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();
                CampaignRecipient::insertOrIgnore($rows);
            }, 'contacts.id', 'id');

            $campaign->update(['recipients_count' => $campaign->recipients()->count()]);
        }

        $campaign->recipients()->where('status', 'pending')->with('contact.subAccount')->chunkById(200, function ($recipients) use ($campaign) {
            foreach ($recipients as $recipient) {
                self::deliver($campaign, $recipient);
            }
        });

        $counts = $campaign->recipients()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_count' => (int) ($counts['sent'] ?? 0),
            'failed_count' => (int) ($counts['failed'] ?? 0),
            'skipped_count' => (int) ($counts['skipped'] ?? 0),
        ]);

        return $campaign;
    }

    private static function deliver(Campaign $campaign, CampaignRecipient $recipient): void
    {
        $contact = $recipient->contact;

        if (! $contact || ! $contact->canReceive($campaign->channel)) {
            $recipient->update(['status' => 'skipped', 'error' => $contact?->dnd ? 'Do not disturb' : 'Opted out or no '.($campaign->channel === 'sms' ? 'phone' : 'email')]);

            return;
        }

        $body = $campaign->body;

        if ($campaign->channel === 'email') {
            $body = self::trackLinks(MergeTags::render($body, $contact), $recipient);
        } else {
            $body = MergeTags::render($body, $contact)."\nReply STOP to opt out.";
        }

        $message = MessageSender::send($contact, $campaign->channel, $body, $campaign->subject, [
            'campaign_id' => $campaign->id,
            'html' => $campaign->channel === 'email',
            'footer' => $campaign->channel === 'email',
            'tracking_pixel' => $campaign->channel === 'email' ? route('tracking.open', $recipient->token) : null,
            'respect_dnd' => true,
        ]);

        $ok = $message->status !== 'failed';
        $recipient->update([
            'message_id' => $message->id,
            'status' => $ok ? 'sent' : 'failed',
            'error' => $ok ? null : $message->error,
            'sent_at' => $ok ? now() : null,
        ]);

        if ($ok) {
            Activity::log($contact, 'campaign', "Received campaign \"{$campaign->name}\"", $campaign);
        }
    }

    /** Rewrite http(s) links in an email so clicks are counted per recipient. */
    public static function trackLinks(string $html, CampaignRecipient $recipient): string
    {
        return preg_replace_callback('/href=(["\'])(https?:\/\/[^"\']+)\1/i', function ($m) use ($recipient) {
            if (str_contains($m[2], '/u/') || str_contains($m[2], '/t/c/')) {
                return $m[0]; // leave unsubscribe and already-tracked links alone
            }

            $url = URL::signedRoute('tracking.click', ['token' => $recipient->token, 'u' => html_entity_decode($m[2])]);

            return 'href='.$m[1].e($url).$m[1];
        }, $html);
    }

    /** Send every scheduled campaign whose time has come. */
    public static function sendDue(): int
    {
        $due = Campaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get();
        $due->each(fn (Campaign $c) => self::send($c));

        return $due->count();
    }
}
