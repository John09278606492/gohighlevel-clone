<?php

namespace App\Flow\Actions;

use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Packstub\Flow\Exceptions\WorkflowException;
use Packstub\Flow\Nodes\Action;
use Packstub\Flow\Nodes\Concerns\InterpolatesPlaceholders;

/** Adds the run's contact to a pipeline as a new opportunity. */
class CreateOpportunity extends Action
{
    use InterpolatesPlaceholders, ResolvesContact;

    public function getName(): string
    {
        return 'Create opportunity';
    }

    public function getDescription(): string
    {
        return 'Adds the contact to a pipeline stage.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-currency-dollar';
    }

    public function getCategory(): string
    {
        return 'CRM';
    }

    public function getFormSchema(): array
    {
        return [
            Select::make('pipeline_id')
                ->label('Pipeline')
                ->options(fn () => Pipeline::where('sub_account_id', static::editingSubAccountId())->pluck('name', 'id'))
                ->live()
                ->required(),
            Select::make('pipeline_stage_id')
                ->label('Stage')
                ->options(fn (Get $get) => PipelineStage::where('pipeline_id', $get('pipeline_id'))->orderBy('sort')->pluck('name', 'id'))
                ->required(),
            TextInput::make('name')
                ->label('Opportunity name')
                ->placeholder('{{ model.full_name }}'),
            TextInput::make('value')
                ->numeric()
                ->default(0),
        ];
    }

    public function handle(array $config, array $payload): void
    {
        $contact = $this->contactFrom($payload);

        if (! $contact) {
            throw new WorkflowException('Create opportunity needs a contact, opportunity or appointment record.');
        }

        $existing = Opportunity::where('contact_id', $contact->id)
            ->where('pipeline_id', $config['pipeline_id'])
            ->where('status', 'open')
            ->first();

        $opportunity = $existing ?? Opportunity::create([
            'sub_account_id' => $contact->sub_account_id,
            'pipeline_id' => $config['pipeline_id'],
            'pipeline_stage_id' => $config['pipeline_stage_id'],
            'contact_id' => $contact->id,
            'name' => $this->interpolate($config['name'] ?? '', $payload) ?: $contact->full_name,
            'value' => (float) ($config['value'] ?? 0),
            'source' => 'Workflow',
        ]);

        $this->output(['opportunity_id' => $opportunity->id, 'created' => ! $existing]);
    }
}
