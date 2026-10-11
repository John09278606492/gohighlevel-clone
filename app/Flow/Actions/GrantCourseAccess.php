<?php

namespace App\Flow\Actions;

use App\Filament\App\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use App\Models\Contact;
use App\Models\Course;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Packstub\Flow\Exceptions\WorkflowException;

class GrantCourseAccess extends ContactAction
{
    public function getName(): string
    {
        return 'Grant course access';
    }

    public function getDescription(): string
    {
        return 'Enrols the contact in a membership course.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public function getFormSchema(): array
    {
        return [
            Select::make('course_id')->label('Course')->options(fn () => Course::where('sub_account_id', static::editingSubAccountId())->pluck('title', 'id'))->required(),
            Toggle::make('notify')->label('Send the access link')->default(true),
        ];
    }

    protected function run(Contact $contact, array $config, array $payload): array
    {
        $course = Course::where('sub_account_id', $contact->sub_account_id)->find($config['course_id'] ?? null)
            ?? throw new WorkflowException('That course no longer exists.');

        $enrollment = $course->enroll($contact, 'workflow');

        if ($config['notify'] ?? false) {
            EnrollmentsRelationManager::sendAccess($enrollment);
        }

        return ['course' => $course->title, 'portal_url' => $enrollment->portalUrl()];
    }
}
