<?php

namespace App\Flow\Actions;

use App\Models\Contact;
use Filament\Forms\Components\TagsInput;

class RemoveContactTag extends ContactAction
{
    public function getName(): string
    {
        return 'Remove contact tag';
    }

    public function getDescription(): string
    {
        return 'Removes tags from the contact.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-x-mark';
    }

    public function getFormSchema(): array
    {
        return [TagsInput::make('tags')->required()];
    }

    protected function run(Contact $contact, array $config, array $payload): array
    {
        $contact->detachTags((array) ($config['tags'] ?? []));

        return ['removed' => $config['tags'] ?? []];
    }
}
