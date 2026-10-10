<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AgencyPanelProvider;
use App\Providers\Filament\AppPanelProvider;

return [
    AppServiceProvider::class,
    AgencyPanelProvider::class,
    AppPanelProvider::class,
];
