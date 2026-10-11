<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Reports\AppointmentsChart;
use App\Filament\App\Reports\CampaignsTable;
use App\Filament\App\Reports\LeadsChart;
use App\Filament\App\Reports\PipelineFunnel;
use App\Filament\App\Reports\ReportKpis;
use App\Filament\App\Reports\RevenueChart;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Agency-grade reporting for one sub-account: attribution, appointments, pipeline, revenue and campaigns. */
class Reports extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'reports';

    protected static ?string $title = 'Reporting';

    protected static ?string $navigationLabel = 'Reporting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reporting';

    protected static ?int $navigationSort = 1;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                DatePicker::make('from')->default(now()->subDays(29))->maxDate(now()),
                DatePicker::make('to')->default(now())->maxDate(now()),
            ])->columnSpanFull(),
        ]);
    }

    public function getWidgets(): array
    {
        return [ReportKpis::class, RevenueChart::class, LeadsChart::class, AppointmentsChart::class, PipelineFunnel::class, CampaignsTable::class];
    }

    public function getColumns(): int|array
    {
        return 3;
    }
}
