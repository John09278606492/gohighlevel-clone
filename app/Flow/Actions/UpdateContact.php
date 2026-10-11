<?php

namespace App\Flow\Actions;

use App\Models\Contact;
use App\Models\CustomField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class UpdateContact extends ContactAction
{
    private const FIELDS = [
        'type' => 'Type (lead / customer)',
        'source' => 'Source',
        'company' => 'Company',
        'owner_id' => 'Assigned user (id)',
        'dnd' => 'Do not disturb (1 / 0)',
    ];

    public function getName(): string
    {
        return 'Update contact field';
    }

    public function getDescription(): string
    {
        return 'Sets a standard or custom field on the contact.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-pencil-square';
    }

    public function getFormSchema(): array
    {
        return [
            Select::make('field')
                ->options(fn () => self::FIELDS + CustomField::where('sub_account_id', static::editingSubAccountId())->get()->mapWithKeys(fn ($f) => ['custom:'.$f->key => 'Custom: '.$f->name])->all())
                ->required(),
            TextInput::make('value')->placeholder('customer, or {{ data.body }}'),
        ];
    }

    protected function run(Contact $contact, array $config, array $payload): array
    {
        $field = (string) ($config['field'] ?? '');
        $value = $this->interpolate((string) ($config['value'] ?? ''), $payload);

        if (str_starts_with($field, 'custom:')) {
            $contact->custom_fields = array_merge((array) $contact->custom_fields, [substr($field, 7) => $value]);
        } elseif (array_key_exists($field, self::FIELDS)) {
            $contact->{$field} = $field === 'dnd' ? (bool) $value : ($value === '' ? null : $value);
        }

        $contact->save();

        return [$field => $value];
    }
}
