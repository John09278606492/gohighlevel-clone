<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::created(function (Message $message): void {
            $conversation = $message->conversation;
            $conversation->last_message_at = $message->created_at;
            if ($message->direction === 'inbound') {
                $conversation->unread_count++;
            }
            $conversation->save();

            $conversation->contact?->forceFill(['last_activity_at' => now()])->saveQuietly();

            if ($message->channel !== 'note' && ! $message->campaign_id) {
                $channel = $message->channel === 'sms' ? 'SMS' : ucfirst($message->channel);
                Activity::log(
                    $conversation->contact,
                    $message->direction === 'inbound' ? 'message_in' : 'message_out',
                    ($message->direction === 'inbound' ? "{$channel} received: " : "{$channel} sent: ").\Illuminate\Support\Str::limit($message->subject ?: strip_tags($message->body), 80),
                    $message,
                );
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
