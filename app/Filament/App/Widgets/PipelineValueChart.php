<?php

namespace App\Filament\App\Widgets;

use App\Models\Pipeline;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class PipelineValueChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Open opportunities by stage';

    protected function getData(): array
    {
        $pipeline = Pipeline::where('sub_account_id', Filament::getTenant()?->getKey())
            ->with(['stages' => fn ($q) => $q->withCount(['opportunities' => fn ($q) => $q->where('status', 'open')])
                ->withSum(['opportunities' => fn ($q) => $q->where('status', 'open')], 'value')])
            ->first();

        $stages = $pipeline?->stages ?? collect();

        return [
            'datasets' => [
                [
                    'label' => 'Value ($)',
                    'data' => $stages->map(fn ($s) => (float) $s->opportunities_sum_value)->all(),
                    'backgroundColor' => '#6366f1',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $stages->map(fn ($s) => "{$s->name} ({$s->opportunities_count})")->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
