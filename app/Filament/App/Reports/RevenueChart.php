<?php

namespace App\Filament\App\Reports;

use App\Filament\App\Reports\Concerns\ReportRange;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Support\Access;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    use ReportRange;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Revenue and won deals';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        [$from, $to] = $this->range();
        [$labels, $monthly] = $this->buckets();
        $key = fn ($date) => $date->format($monthly ? 'Y-m' : 'Y-m-d');

        $series = function ($rows, string $dateColumn, string $valueColumn) use ($labels, $key) {
            $totals = array_fill_keys(array_keys($labels), 0.0);
            foreach ($rows as $row) {
                $k = $key($row->{$dateColumn});
                if (isset($totals[$k])) {
                    $totals[$k] += (float) $row->{$valueColumn};
                }
            }

            return array_values($totals);
        };

        $payments = Payment::where('sub_account_id', Access::tenantId())->whereBetween('paid_at', [$from, $to])->get(['paid_at', 'amount']);
        $won = Opportunity::where('sub_account_id', Access::tenantId())->where('status', 'won')->whereBetween('updated_at', [$from, $to])->get(['updated_at', 'value']);

        return [
            'datasets' => [
                ['label' => 'Payments collected', 'data' => $series($payments, 'paid_at', 'amount'), 'backgroundColor' => '#22c55e', 'borderColor' => '#22c55e'],
                ['label' => 'Deals won (value)', 'data' => $series($won, 'updated_at', 'value'), 'backgroundColor' => '#6366f1', 'borderColor' => '#6366f1'],
            ],
            'labels' => array_values($labels),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
