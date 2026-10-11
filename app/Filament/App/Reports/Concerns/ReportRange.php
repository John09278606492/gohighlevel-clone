<?php

namespace App\Filament\App\Reports\Concerns;

use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Reads the Reports page date range (defaults to the last 30 days). */
trait ReportRange
{
    use InteractsWithPageFilters;

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    protected function range(): array
    {
        $from = $this->pageFilters['from'] ?? null;
        $to = $this->pageFilters['to'] ?? null;

        return [
            $from ? CarbonImmutable::parse($from)->startOfDay() : CarbonImmutable::now()->subDays(29)->startOfDay(),
            $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay(),
        ];
    }

    /** Daily buckets when the range is short, monthly when it is long. */
    protected function buckets(): array
    {
        [$from, $to] = $this->range();
        $monthly = $from->diffInDays($to) > 92;
        $labels = [];
        $cursor = $monthly ? $from->startOfMonth() : $from;

        while ($cursor <= $to) {
            $labels[$cursor->format($monthly ? 'Y-m' : 'Y-m-d')] = $cursor->format($monthly ? 'M Y' : 'M j');
            $cursor = $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        return [$labels, $monthly];
    }
}
