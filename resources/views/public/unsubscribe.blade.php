<x-layouts.public :title="'Email preferences · '.$contact->subAccount->name">
    <main class="ghl-doc ghl-doc--narrow">
        @include('public.partials.brand', ['account' => $contact->subAccount])

        <section class="ghl-card">
            @if ($done)
                <h1>You're unsubscribed</h1>
                <p class="ghl-doc__muted">{{ $contact->subAccount->name }} won't send you these messages any more. Changed your mind? Just reply to any earlier message.</p>
            @else
                <h1>Unsubscribe</h1>
                <p class="ghl-doc__muted">Choose what {{ $contact->subAccount->name }} should stop sending to {{ $contact->email ?? $contact->phone }}.</p>
                <form method="post" action="{{ route('unsubscribe.confirm', $contact->unsubscribe_token) }}" class="ghl-stack">
                    @csrf
                    <label class="ghl-radio"><input type="radio" name="channel" value="email" checked> Marketing emails</label>
                    <label class="ghl-radio"><input type="radio" name="channel" value="sms"> Text messages</label>
                    <label class="ghl-radio"><input type="radio" name="channel" value="all"> Everything</label>
                    <button class="ghl-button" type="submit">Unsubscribe</button>
                </form>
            @endif
        </section>
    </main>
</x-layouts.public>
