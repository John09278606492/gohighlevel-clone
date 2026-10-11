<?php

namespace App\Flow\Actions;

use App\Models\Contact;
use Filament\Forms\Components\TagsInput;

class AddContactTag extends ContactAction
{
    public function getName(): string
    {
        return 'Add contact tag';
    }

    public function getDescription(): string
    {
        return 'Tags the contact (fires "Tag added" workflows).';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-tag';
    }

    public function getFormSchema(): array
    {
        return [TagsInput::make('tags')->required()];
    }

    protected function run(Contact $contact, array $config, array $payload): array
    {
        $tags = array_values(array_filter(array_map(fn ($t) => trim($this->interpolate((string) $t, $payload)), (array) ($config['tags'] ?? []))));
        $contact->attachTags($tags);

        return ['tags' => $tags];
    }
}
