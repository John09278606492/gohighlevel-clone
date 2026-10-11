<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\TriggerLink;
use App\Support\CrmEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/** Trigger links, campaign open/click tracking and unsubscribe. */
class TrackingController extends Controller
{
    public function link(Request $request, string $code)
    {
        $link = TriggerLink::where('code', $code)->firstOrFail();
        $contact = Contact::findByTrackingToken($request->query('c'));

        if ($contact && $contact->sub_account_id !== $link->sub_account_id) {
            $contact = null;
        }

        $firstForContact = $contact && ! $link->clicks()->where('contact_id', $contact->id)->exists();

        $link->clicks()->create([
            'contact_id' => $contact?->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
        $link->increment('clicks_count', 1, $firstForContact ? ['unique_contacts_count' => DB::raw('unique_contacts_count + 1')] : []);

        if ($contact) {
            if ($link->add_tags) {
                $contact->attachTags($link->add_tags);
            }
            Activity::log($contact, 'link_click', "Clicked trigger link \"{$link->name}\"", $link);
            CrmEvents::fire('trigger_link_clicked', $contact, $link, ['link' => $link->name, 'url' => $link->url]);
        }

        return redirect()->away($link->url);
    }

    public function open(string $token)
    {
        if ($recipient = CampaignRecipient::where('token', $token)->first()) {
            if (! $recipient->opened_at) {
                $recipient->update(['opened_at' => now()]);
                $recipient->campaign()->increment('opened_count');
                CrmEvents::fire('email_opened', $recipient->contact, $recipient->campaign, ['campaign' => $recipient->campaign->name]);
            }
        }

        // 1x1 transparent GIF
        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /** Signed redirect so the tracker can't be used as an open redirect. */
    public function click(Request $request, string $token)
    {
        abort_unless(URL::hasValidSignature($request), 403);

        $url = (string) $request->query('u');
        abort_unless(preg_match('~^https?://~i', $url), 400);

        if ($recipient = CampaignRecipient::where('token', $token)->first()) {
            if (! $recipient->clicked_at) {
                $recipient->update(['clicked_at' => now(), 'opened_at' => $recipient->opened_at ?? now()]);
                $recipient->campaign()->increment('clicked_count');
            }
            Activity::log($recipient->contact, 'link_click', "Clicked {$url} in campaign \"{$recipient->campaign->name}\"", $recipient->campaign);
        }

        return redirect()->away($url);
    }

    public function unsubscribe(string $token)
    {
        $contact = Contact::where('unsubscribe_token', $token)->firstOrFail();

        return view('public.unsubscribe', ['contact' => $contact, 'done' => false]);
    }

    public function confirmUnsubscribe(Request $request, string $token)
    {
        $contact = Contact::where('unsubscribe_token', $token)->firstOrFail();
        $channel = $request->input('channel', 'email');

        $contact->forceFill(match ($channel) {
            'sms' => ['dnd_sms' => true],
            'all' => ['dnd' => true, 'dnd_email' => true, 'dnd_sms' => true],
            default => ['dnd_email' => true],
        })->save();

        Activity::log($contact, 'dnd', 'Unsubscribed from '.($channel === 'all' ? 'all messages' : $channel));
        CrmEvents::fire('contact_unsubscribed', $contact, null, ['channel' => $channel]);

        // Count it against the most recent campaign this contact received.
        $recipient = CampaignRecipient::where('contact_id', $contact->id)->latest()->first();
        $recipient?->campaign()->increment('unsubscribed_count');

        return view('public.unsubscribe', ['contact' => $contact, 'done' => true]);
    }
}
