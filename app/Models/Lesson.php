<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_preview' => 'boolean'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** An embeddable player URL for YouTube, Vimeo or Loom links; null for anything else. */
    public function embedUrl(): ?string
    {
        $url = (string) $this->video_url;

        return match (true) {
            (bool) preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~', $url, $m) => "https://www.youtube.com/embed/{$m[1]}",
            (bool) preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m) => "https://player.vimeo.com/video/{$m[1]}",
            (bool) preg_match('~loom\.com/(?:share|embed)/(\w+)~', $url, $m) => "https://www.loom.com/embed/{$m[1]}",
            default => null,
        };
    }
}
