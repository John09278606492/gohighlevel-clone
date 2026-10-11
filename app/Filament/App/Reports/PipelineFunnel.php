<?php

namespace App\Filament\App\Reports;

use App\Models\Pipeline;
use App\Support\Access;
use Filament\Widgets\ChartWidget;

/** How many open opportunities sit in each stage of the main pipeline, and what they're worth. */
class PipelineFunnel extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Pipeline by stage';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $pipeline = Pipeline::with(['stages' => fn ($q) => $q->withCount(['opportunities' => fn ($q) => $q->where('status', 'open')])->withSum(['opportunities' => fn ($q) => $q->where('status', 'open')], 'value')])
            ->where('sub_account_id', Access::tenantId())->first();
        $stages = $pipeline?->stages ?? collect();

        return [
            'datasets' => [
                ['label' => 'Open opportunities', 'data' => $stages->pluck('opportunities_count')->all(), 'backgroundColor' => '#6366f1', 'yAxisID' => 'y'],
                ['label' => 'Value', 'data' => $stages->map(fn ($s) => (float) $s->opportunities_sum_value)->all(), 'backgroundColor' => '#a5b4fc', 'yAxisID' => 'y1'],
            ],
            'labels' => $stages->pluck('name')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['position' => 'left'], 'y1' => ['position' => 'right', 'grid' => ['drawOnChartArea' => false]]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
