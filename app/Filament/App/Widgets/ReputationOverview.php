<?php

namespace App\Filament\App\Widgets;

use App\Models\Review;
use App\Models\ReviewRequest;
use App\Support\Access;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReputationOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $tenant = Access::tenantId();
        $reviews = Review::where('sub_account_id', $tenant);
        $requests = ReviewRequest::where('sub_account_id', $tenant)->where('created_at', '>=', now()->subDays(30));
        $sent = (clone $requests)->count();
        $completed = (clone $requests)->whereNotNull('completed_at')->count();

        return [
            Stat::make('Average rating', number_format((float) (clone $reviews)->avg('rating'), 1).' ★')->description((clone $reviews)->count().' reviews'),
            Stat::make('Last 30 days', (clone $reviews)->where('created_at', '>=', now()->subDays(30))->count().' new reviews'),
            Stat::make('Requests answered', $sent ? round($completed / $sent * 100).'%' : '-')->description("{$completed} of {$sent} requests (30 days)"),
            Stat::make('Needs a reply', (clone $reviews)->where('rating', '<=', 3)->whereNull('replied_at')->count())->color('warning'),
        ];
    }
}
