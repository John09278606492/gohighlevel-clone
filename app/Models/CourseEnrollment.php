<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CourseEnrollment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_accessed_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (CourseEnrollment $e) => $e->token ??= Str::random(40));
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function portalUrl(): string
    {
        return route('portal.course', $this->token);
    }

    public function progress(): int
    {
        $total = $this->course->lessons()->count();

        return $total ? (int) round($this->completions()->count() / $total * 100) : 0;
    }
}
