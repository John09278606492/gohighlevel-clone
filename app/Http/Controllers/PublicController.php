<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Page;
use App\Models\SubAccount;
use Illuminate\Http\Request;

/** Public, unauthenticated pages: booking calendars and funnel/website pages. */
class PublicController extends Controller
{
    public function booking(Request $request, SubAccount $subAccount, string $calendar)
    {
        $calendar = Calendar::where('sub_account_id', $subAccount->id)
            ->where('slug', $calendar)
            ->where('is_active', true)
            ->firstOrFail();

        return view('booking.show', [
            'calendar' => $calendar,
            'embed' => $request->boolean('embed'),
        ]);
    }

    public function page(Request $request, SubAccount $subAccount, ?string $slug = null)
    {
        $page = Page::where('sub_account_id', $subAccount->id)
            ->when($slug, fn ($q) => $q->where('slug', $slug), fn ($q) => $q->orderBy('step_order'))
            ->firstOrFail();

        // Drafts are visible to logged-in team members of the sub-account (preview), not the public.
        if (! $page->is_published && ! $request->user()?->canAccessTenant($subAccount)) {
            abort(404);
        }

        if ($page->is_published) {
            $page->increment('views');
        }

        app()->instance('funnels.page', $page);

        return view('pages.show', ['page' => $page]);
    }
}
