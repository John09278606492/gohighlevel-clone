<?php

namespace App\Flow\Actions;

use App\Models\Contact;
use Packstub\Flow\Exceptions\WorkflowException;
use Packstub\Flow\Nodes\Action;
use Packstub\Flow\Nodes\Concerns\InterpolatesPlaceholders;

/** Base for workflow actions that act on the run's contact. */
abstract class ContactAction extends Action
{
    use InterpolatesPlaceholders, ResolvesContact;

    public function getCategory(): string
    {
        return 'CRM';
    }

    public function handle(array $config, array $payload): void
    {
        $contact = $this->contactFrom($payload);

        if (! $contact) {
            throw new WorkflowException($this->getName().' needs a contact, opportunity or appointment record.');
        }

        $this->output($this->run($contact, $config, $payload));
    }

    /** @return array<string, mixed> output shown in the run log */
    abstract protected function run(Contact $contact, array $config, array $payload): array;
}
