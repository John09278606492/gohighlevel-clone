<?php

namespace App\Filament\App\Widgets;

use App\Models\Contact;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class LeadSourcesChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Lead sources (last 30 days)';

    protected function getData(): array
    {
        $rows = Contact::where('sub_account_id', Filament::getTenant()?->getKey())
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("coalesce(source, 'Unknown') as label, count(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return [
            'datasets' => [[
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#a855f7'],
            ]],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
