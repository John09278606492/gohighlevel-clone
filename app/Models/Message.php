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
