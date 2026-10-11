<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use App\Support\CrmEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A membership course: lessons grouped by module, unlocked per contact with a private link. */
class Course extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function enroll(Contact $contact, string $source = 'manual'): CourseEnrollment
    {
        $enrollment = $this->enrollments()->firstOrCreate(['contact_id' => $contact->id], ['source' => $source]);

        if ($enrollment->wasRecentlyCreated) {
            Activity::log($contact, 'course', "Enrolled in course \"{$this->title}\"", $this, ['source' => $source]);
            CrmEvents::fire('course_enrolled', $contact, $enrollment, ['course' => $this->title, 'portal_url' => $enrollment->portalUrl()]);
        }

        return $enrollment;
    }
}
