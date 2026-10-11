<?php

namespace App\Filament\App\Reports;

use App\Filament\App\Reports\Concerns\ReportRange;
use App\Models\Appointment;
use App\Support\Access;
use Filament\Widgets\ChartWidget;

class AppointmentsChart extends ChartWidget
{
    use ReportRange;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Appointments by outcome';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        [$from, $to] = $this->range();
        $counts = Appointment::where('sub_account_id', Access::tenantId())->whereBetween('starts_at', [$from, $to])
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $statuses = Appointment::STATUSES;

        return [
            'datasets' => [['label' => 'Appointments', 'data' => array_map(fn ($s) => (int) ($counts[$s] ?? 0), array_keys($statuses)), 'backgroundColor' => ['#94a3b8', '#06b6d4', '#22c55e', '#ef4444', '#f59e0b']]],
            'labels' => array_values($statuses),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
