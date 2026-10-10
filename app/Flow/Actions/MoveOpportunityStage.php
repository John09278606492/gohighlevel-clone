<?php

namespace App\Flow\Actions;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use Filament\Forms\Components\Select;
use Packstub\Flow\Exceptions\WorkflowException;
use Packstub\Flow\Nodes\Action;

/** Moves an opportunity (or the contact's open opportunity) to another stage, or marks it won/lost. */
class MoveOpportunityStage extends Action
{
    use ResolvesContact;

    public function getName(): string
    {
        return 'Move opportunity';
    }

    public function getDescription(): string
    {
        return 'Changes the stage or status of an opportunity.';
    }

    public function getIcon(): ?string
    {
        return 'heroicon-o-arrows-right-left';
    }

    public function getCategory(): string
    {
        return 'CRM';
    }

    public function getFormSchema(): array
    {
        return [
            Select::make('pipeline_stage_id')
                ->label('Move to stage')
                ->options(fn () => PipelineStage::query()
                    ->whereHas('pipeline', fn ($q) => $q->where('sub_account_id', static::editingSubAccountId()))
                    ->with('pipeline')
                    ->orderBy('pipeline_id')->orderBy('sort')
                    ->get()
                    ->mapWithKeys(fn (PipelineStage $stage) => [$stage->id => "{$stage->pipeline->name} → {$stage->name}"])),
            Select::make('status')
                ->options(Opportunity::STATUSES)
                ->placeholder('Leave unchanged'),
        ];
    }

    public function handle(array $config, array $payload): void
    {
        $model = $payload['model'] ?? null;

        $opportunity = $model instanceof Opportunity
            ? $model
            : Opportunity::where('contact_id', $this->contactFrom($payload)?->id ?? 0)->where('status', 'open')->latest()->first();

        if (! $opportunity) {
            throw new WorkflowException('No open opportunity found for this record.');
        }

        $changes = array_filter([
            'pipeline_stage_id' => $config['pipeline_stage_id'] ?? null,
            'status' => $config['status'] ?? null,
        ]);

        if (isset($changes['pipeline_stage_id'])) {
            $changes['position'] = bcadd((string) (Opportunity::where('pipeline_stage_id', $changes['pipeline_stage_id'])->max('position') ?? 0), '65535', 10);
        }

        $opportunity->update($changes);

        $this->output(['opportunity_id' => $opportunity->id] + $changes);
    }
}
