<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Support\CrmEvents;

/** The member portal: a contact opens their course with the private link they were sent. */
class PortalController extends Controller
{
    public function course(string $token)
    {
        $enrollment = $this->enrollment($token);

        return view('public.portal.course', [
            'enrollment' => $enrollment,
            'course' => $enrollment->course,
            'completed' => $enrollment->completions()->pluck('lesson_id')->all(),
        ]);
    }

    public function lesson(string $token, Lesson $lesson)
    {
        $enrollment = $this->enrollment($token);
        abort_unless($lesson->course_id === $enrollment->course_id, 404);

        $lessons = $enrollment->course->lessons;
        $index = $lessons->search(fn ($l) => $l->id === $lesson->id);

        return view('public.portal.lesson', [
            'enrollment' => $enrollment,
            'course' => $enrollment->course,
            'lesson' => $lesson,
            'previous' => $index > 0 ? $lessons[$index - 1] : null,
            'next' => $lessons[$index + 1] ?? null,
            'completed' => $enrollment->completions()->pluck('lesson_id')->all(),
        ]);
    }

    public function complete(string $token, Lesson $lesson)
    {
        $enrollment = $this->enrollment($token);
        abort_unless($lesson->course_id === $enrollment->course_id, 404);

        $enrollment->completions()->firstOrCreate(['lesson_id' => $lesson->id], ['completed_at' => now()]);

        if (! $enrollment->completed_at && $enrollment->progress() >= 100) {
            $enrollment->update(['completed_at' => now()]);
            Activity::log($enrollment->contact, 'course', "Completed course \"{$enrollment->course->title}\"", $enrollment->course);
            CrmEvents::fire('course_completed', $enrollment->contact, $enrollment, ['course' => $enrollment->course->title]);
        }

        $next = $enrollment->course->lessons()->where('sort', '>', $lesson->sort)->first();

        return $next
            ? redirect()->route('portal.lesson', [$token, $next])
            : redirect()->route('portal.course', $token);
    }

    private function enrollment(string $token): CourseEnrollment
    {
        $enrollment = CourseEnrollment::with('course.lessons', 'contact')->where('token', $token)->firstOrFail();
        abort_unless($enrollment->course->is_published, 404);
        $enrollment->forceFill(['last_accessed_at' => now()])->saveQuietly();

        return $enrollment;
    }
}
