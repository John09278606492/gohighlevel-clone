<?php

namespace App\Services;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Publishes scheduled social posts by POSTing them to the sub-account's social
 * webhook (Zapier, Make, n8n, Buffer...), which fans them out to the networks.
 * Without a webhook the post is marked published so the planner still works as a content calendar.
 */
class SocialPublisher
{
    public static function publish(SocialPost $post): SocialPost
    {
        $account = $post->subAccount;

        if ($url = $account->social_webhook_url) {
            try {
                $response = Http::timeout(20)->post($url, [
                    'id' => $post->id,
                    'business' => $account->name,
                    'content' => $post->content,
                    'platforms' => $post->platforms ?? [],
                    'link' => $post->link,
                    'image_url' => $post->image ? Storage::disk('public')->url($post->image) : null,
                    'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                ]);

                if ($response->failed()) {
                    $post->update(['status' => 'failed', 'error' => 'Webhook answered HTTP '.$response->status()]);

                    return $post;
                }
            } catch (\Throwable $e) {
                $post->update(['status' => 'failed', 'error' => $e->getMessage()]);

                return $post;
            }
        }

        $post->update(['status' => 'published', 'published_at' => now(), 'error' => null]);

        return $post;
    }

    public static function publishDue(): int
    {
        $due = SocialPost::with('subAccount')->where('status', 'scheduled')->where('scheduled_at', '<=', now())->get();
        $due->each(fn (SocialPost $post) => self::publish($post));

        return $due->count();
    }
}
