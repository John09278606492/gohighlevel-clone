<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Packstub\Flow\Concerns\HasWorkflows;

class Opportunity extends Model
{
    use BelongsToSubAccount, HasWorkflows;

    protected $guarded = [];

    public const STATUSES = [
        'open' => 'Open',
        'won' => 'Won',
        'lost' => 'Lost',
        'abandoned' => 'Abandoned',
    ];

    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(function (Opportunity $opportunity): void {
            if (blank($opportunity->pipeline_stage_id) && $opportunity->pipeline_id) {
                $opportunity->pipeline_stage_id = PipelineStage::where('pipeline_id', $opportunity->pipeline_id)->orderBy('sort')->value('id');
            }

            if (blank($opportunity->position)) {
                $last = static::where('pipeline_stage_id', $opportunity->pipeline_stage_id)->max('position');
                $opportunity->position = $last === null ? '65535' : bcadd((string) $last, '65535', 10);
            }
        });

        // Keep pipeline_id in sync when a card is dragged to a stage.
        static::saving(function (Opportunity $opportunity): void {
            if ($opportunity->isDirty('pipeline_stage_id') && $opportunity->pipeline_stage_id) {
                $opportunity->pipeline_id = PipelineStage::whereKey($opportunity->pipeline_stage_id)->value('pipeline_id') ?? $opportunity->pipeline_id;
            }
        });
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
