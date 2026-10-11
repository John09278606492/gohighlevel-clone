<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Review;
use App\Models\ReviewRequest;
use App\Models\SubAccount;
use App\Support\CrmEvents;
use Illuminate\Http\Request;

/**
 * The review funnel: happy customers (at or above the threshold) are sent on to
 * Google / Facebook, unhappy ones leave private feedback for the business instead.
 */
class ReviewController extends Controller
{
    public function show(string $token)
    {
        $request = ReviewRequest::with('subAccount', 'contact', 'review')->where('token', $token)->firstOrFail();

        if (! $request->opened_at) {
            $request->update(['opened_at' => now()]);
        }

        return view('public.review', ['account' => $request->subAccount, 'reviewRequest' => $request, 'review' => $request->review]);
    }

    public function showGeneric(SubAccount $subAccount)
    {
        return view('public.review', ['account' => $subAccount, 'reviewRequest' => null, 'review' => null]);
    }

    public function store(Request $request, ?string $token = null, ?SubAccount $subAccount = null)
    {
        $reviewRequest = $token ? ReviewRequest::where('token', $token)->firstOrFail() : null;
        $account = $reviewRequest?->subAccount ?? $subAccount;
        abort_unless($account, 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'author_name' => ['nullable', 'string', 'max:120'],
        ]);

        if ($reviewRequest?->review) {
            return redirect()->back();
        }

        $happy = $data['rating'] >= $account->review_threshold;

        $review = Review::create([
            'sub_account_id' => $account->id,
            'contact_id' => $reviewRequest?->contact_id,
            'review_request_id' => $reviewRequest?->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'author_name' => $data['author_name'] ?? $reviewRequest?->contact?->full_name,
            'source' => 'direct',
            'is_public' => $happy,
        ]);

        $reviewRequest?->update(['completed_at' => now()]);

        if ($contact = $reviewRequest?->contact) {
            Activity::log($contact, 'review', "Left a {$review->rating}-star review", $review);
            CrmEvents::fire('review_received', $contact, $review, ['rating' => $review->rating, 'comment' => $review->comment]);
        }

        return view('public.review', [
            'account' => $account,
            'reviewRequest' => $reviewRequest,
            'review' => $review,
            'justSubmitted' => true,
        ]);
    }
}
