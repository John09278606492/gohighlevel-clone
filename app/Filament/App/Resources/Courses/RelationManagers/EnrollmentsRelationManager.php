<?php

namespace App\Filament\App\Resources\Courses\RelationManagers;

use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Services\MessageSender;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact.full_name')->label('Member')->description(fn (CourseEnrollment $r) => $r->contact?->email),
                TextColumn::make('progress')->state(fn (CourseEnrollment $r) => $r->progress().'%'),
                TextColumn::make('source')->badge(),
                TextColumn::make('last_accessed_at')->since()->placeholder('Never'),
                TextColumn::make('created_at')->label('Joined')->date(),
            ])
            ->headerActions([
                Action::make('enroll')->label('Add member')->icon('heroicon-o-user-plus')
                    ->schema([
                        Select::make('contact_id')->label('Contact')->searchable()->required()
                            ->getSearchResultsUsing(fn (string $search) => Contact::where('sub_account_id', $this->getOwnerRecord()->sub_account_id)
                                ->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                                ->limit(20)->get()->mapWithKeys(fn ($c) => [$c->id => $c->full_name.($c->email ? " ({$c->email})" : '')]))
                            ->getOptionLabelUsing(fn ($value) => Contact::find($value)?->full_name),
                        Toggle::make('notify')->label('Email them their access link')->default(true),
                    ])
                    ->action(function (array $data): void {
                        /** @var Course $course */
                        $course = $this->getOwnerRecord();
                        $contact = Contact::where('sub_account_id', $course->sub_account_id)->findOrFail($data['contact_id']);
                        $enrollment = $course->enroll($contact);

                        if ($data['notify'] ?? false) {
                            static::sendAccess($enrollment);
                        }

                        Notification::make()->success()->title("{$contact->full_name} can now open {$course->title}")->send();
                    }),
            ])
            ->recordActions([
                Action::make('sendLink')->label('Send access link')->icon('heroicon-o-envelope')->action(fn (CourseEnrollment $record) => static::sendAccess($record)),
                Action::make('copy')->label('Portal link')->icon('heroicon-o-link')->url(fn (CourseEnrollment $r) => $r->portalUrl(), true),
                DeleteAction::make()->label('Remove access'),
            ]);
    }

    public static function sendAccess(CourseEnrollment $enrollment): void
    {
        $course = $enrollment->course;
        $channel = $enrollment->contact->canReceive('email') ? 'email' : 'sms';
        $body = $channel === 'email'
            ? '<p>Hi {{ contact.first_name }},</p><p>You now have access to <strong>'.e($course->title).'</strong>.</p><p><a href="'.$enrollment->portalUrl().'">Start learning</a></p><p>Keep this email: the link is your personal access.</p>'
            : "Hi {{ contact.first_name }}, you now have access to {$course->title}: {$enrollment->portalUrl()}";

        MessageSender::send($enrollment->contact, $channel, $body, 'Your access to '.$course->title, ['html' => $channel === 'email']);
    }
}
