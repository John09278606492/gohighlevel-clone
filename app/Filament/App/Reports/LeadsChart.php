<?php

namespace App\Filament\App\Reports;

use App\Filament\App\Reports\Concerns\ReportRange;
use App\Models\Contact;
use App\Support\Access;
use Filament\Widgets\ChartWidget;

/** Lead attribution: new contacts by source over the period. */
class LeadsChart extends ChartWidget
{
    use ReportRange;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Lead attribution';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        [$from, $to] = $this->range();

        $rows = Contact::where('sub_account_id', Access::tenantId())
            ->whereBetween('created_at', [$from, $to])
            ->get(['source'])
            ->groupBy(fn ($c) => trim(explode(':', (string) $c->source)[0]) ?: 'Unknown')
            ->map->count()
            ->sortDesc()
            ->take(8);

        return [
            'datasets' => [['label' => 'Leads', 'data' => $rows->values()->all(), 'backgroundColor' => ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#a855f7', '#ec4899', '#64748b']]],
            'labels' => $rows->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
