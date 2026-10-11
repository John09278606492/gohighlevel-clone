<?php

namespace App\Filament\App\Reports;

use App\Filament\App\Reports\Concerns\ReportRange;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\Review;
use App\Support\Access;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReportKpis extends StatsOverviewWidget
{
    use ReportRange;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $t = Access::tenantId();
        [$from, $to] = $this->range();
        $currency = Filament::getTenant()?->currency ?? 'USD';
        $between = fn ($q, $col = 'created_at') => $q->whereBetween($col, [$from, $to]);

        $leads = $between(Contact::where('sub_account_id', $t))->count();
        $appointments = $between(Appointment::where('sub_account_id', $t), 'starts_at');
        $booked = (clone $appointments)->count();
        $held = (clone $appointments)->whereIn('status', ['showed', 'no_show'])->count();
        $showed = (clone $appointments)->where('status', 'showed')->count();
        $won = $between(Opportunity::where('sub_account_id', $t)->where('status', 'won'), 'updated_at');
        $closed = $between(Opportunity::where('sub_account_id', $t)->whereIn('status', ['won', 'lost']), 'updated_at')->count();
        $revenue = (float) $between(Payment::where('sub_account_id', $t), 'paid_at')->sum('amount');
        $inbound = $between(Message::whereHas('conversation', fn ($q) => $q->where('sub_account_id', $t))->where('direction', 'inbound'))->count();
        $outbound = $between(Message::whereHas('conversation', fn ($q) => $q->where('sub_account_id', $t))->where('direction', 'outbound')->where('channel', '!=', 'note'))->count();
        $rating = $between(Review::where('sub_account_id', $t))->avg('rating');

        return [
            Stat::make('New leads', number_format($leads)),
            Stat::make('Appointments', number_format($booked))->description($held ? 'Show rate '.round($showed / $held * 100).'%' : 'No completed appointments yet'),
            Stat::make('Deals won', $currency.' '.number_format((float) (clone $won)->sum('value')))->description((clone $won)->count().' won · win rate '.($closed ? round((clone $won)->count() / $closed * 100) : 0).'%')->color('success'),
            Stat::make('Revenue collected', $currency.' '.number_format($revenue, 2))->color('success'),
            Stat::make('Lead to customer', $leads ? round($between(Contact::where('sub_account_id', $t)->where('type', 'customer'))->count() / $leads * 100).'%' : '-')->description('of new leads became customers'),
            Stat::make('Messages', number_format($outbound).' sent')->description(number_format($inbound).' replies received'),
            Stat::make('Average review', $rating ? number_format((float) $rating, 1).' ★' : '-'),
            Stat::make('Open pipeline', $currency.' '.number_format((float) Opportunity::where('sub_account_id', $t)->where('status', 'open')->sum('value'))),
        ];
    }
}
