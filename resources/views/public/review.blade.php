<x-layouts.public :title="'Review '.$account->name">
    <main class="ghl-doc ghl-doc--narrow">
        @include('public.partials.brand', ['account' => $account])

        <section class="ghl-card">
            @if ($review)
                @if ($review->rating >= $account->review_threshold && ($account->google_review_url || $account->facebook_review_url))
                    <h1>Thank you{{ $review->author_name ? ', '.explode(' ', $review->author_name)[0] : '' }}!</h1>
                    <p class="ghl-doc__muted">Would you share that on one of these sites too? It takes a minute and helps other people find {{ $account->name }}.</p>
                    <div class="ghl-stack">
                        @if ($account->google_review_url)<a class="ghl-button" href="{{ $account->google_review_url }}" rel="noopener">Review us on Google</a>@endif
                        @if ($account->facebook_review_url)<a class="ghl-button ghl-button--secondary" href="{{ $account->facebook_review_url }}" rel="noopener">Review us on Facebook</a>@endif
                    </div>
                @elseif ($review->rating >= $account->review_threshold)
                    <h1>Thank you!</h1>
                    <p class="ghl-doc__muted">We really appreciate your feedback.</p>
                @else
                    <h1>Thank you for telling us</h1>
                    <p class="ghl-doc__muted">We're sorry it wasn't a great experience. Your feedback went straight to the team at {{ $account->name }}, and someone will be in touch.</p>
                @endif
            @else
                <h1>How was your experience with {{ $account->name }}?</h1>
                <form method="post" action="{{ $reviewRequest ? route('reviews.submit', $reviewRequest->token) : route('reviews.generic.submit', $account) }}" class="ghl-stack" x-data="{ rating: 0 }">
                    @csrf
                    <div class="ghl-stars" role="radiogroup" aria-label="Rating">
                        @foreach (range(1, 5) as $star)
                            <label :class="{ 'is-on': rating >= {{ $star }} }">
                                <input type="radio" name="rating" value="{{ $star }}" x-model.number="rating" required>
                                <span aria-hidden="true">★</span><span class="sr-only">{{ $star }} stars</span>
                            </label>
                        @endforeach
                    </div>
                    @unless ($reviewRequest)
                        <input class="ghl-input" type="text" name="author_name" placeholder="Your name" maxlength="120">
                    @endunless
                    <textarea class="ghl-input" name="comment" rows="4" placeholder="Tell us more (optional)" maxlength="5000"></textarea>
                    @error('rating')<p class="ghl-error">Please pick a rating.</p>@enderror
                    <button class="ghl-button" type="submit" :disabled="!rating">Submit</button>
                </form>
            @endif
        </section>
    </main>
</x-layouts.public>
