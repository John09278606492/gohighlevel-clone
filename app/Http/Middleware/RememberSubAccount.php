<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the last sub-account the user opened, so the Agency panel can offer
 * a "Back to <sub-account>" link.
 */
class RememberSubAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($tenant = Filament::getTenant()) {
            $request->session()->put('last_sub_account_id', $tenant->getKey());
        }

        return $next($request);
    }
}
