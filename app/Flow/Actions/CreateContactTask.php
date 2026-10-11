<?php

namespace App\Flow\Actions;

use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class CreateContactTask extends ContactAction
{
    public function getName(): string
    {
        return 'Create task';
    }

    public function getDescription(): string
    {
        return 'Adds a follow-up task for the team about this contact.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-check-circle';
    }

    public function getFormSchema(): array
    {
        return [
            TextInput::make('title')->required()->placeholder('Call {{ model.first_name }}'),
            Textarea::make('description')->rows(3),
            TextInput::make('due_in_hours')->label('Due in (hours)')->numeric()->default(24),
            Select::make('assign')->label('Assign to')
                ->options(fn () => ['owner' => 'Contact owner'] + User::whereHas('subAccounts', fn ($q) => $q->whereKey(static::editingSubAccountId()))->pluck('name', 'id')->all())
                ->default('owner'),
        ];
    }

    protected function run(Contact $contact, array $config, array $payload): array
    {
        $task = Task::create([
            'sub_account_id' => $contact->sub_account_id,
            'contact_id' => $contact->id,
            'assigned_to' => ($config['assign'] ?? 'owner') === 'owner' ? $contact->owner_id : (int) $config['assign'],
            'title' => $this->interpolate((string) ($config['title'] ?? 'Follow up'), $payload),
            'description' => $this->interpolate((string) ($config['description'] ?? ''), $payload) ?: null,
            'due_at' => now()->addHours((int) ($config['due_in_hours'] ?? 24)),
        ]);

        return ['task_id' => $task->id];
    }
}
