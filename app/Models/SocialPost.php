<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected $attributes = ['status' => 'draft'];

    public const PLATFORMS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'x' => 'X (Twitter)',
        'google_business' => 'Google Business Profile',
        'tiktok' => 'TikTok',
    ];

    public const STATUSES = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published', 'failed' => 'Failed'];

    protected function casts(): array
    {
        return ['platforms' => 'array', 'scheduled_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
