@php($account = $document->subAccount)
<x-layouts.public :title="$document->title.' · '.$account->name">
    <main class="ghl-doc">
        @include('public.partials.brand', ['account' => $account])

        @if ($document->status === 'signed')
            <div class="ghl-alert ghl-alert--success">Signed by {{ $document->signer_name }} on {{ $document->signed_at->format('M j, Y \a\t g:i A') }}.</div>
        @elseif ($document->status === 'declined')
            <div class="ghl-alert ghl-alert--danger">This document was declined.</div>
        @elseif ($document->isExpired())
            <div class="ghl-alert ghl-alert--warning">This document expired on {{ $document->expires_on->format('M j, Y') }}.</div>
        @elseif ($document->status === 'draft')
            <div class="ghl-alert ghl-alert--warning">Draft preview: the client can't see this until you send it.</div>
        @endif

        <article class="ghl-card ghl-prose">
            <h1>{{ $document->title }}</h1>
            @if ($document->amount)
                <p class="ghl-doc__muted">Amount: {{ $account->currency }} {{ number_format((float) $document->amount, 2) }}</p>
            @endif
            {!! str($document->body)->sanitizeHtml() !!}
        </article>

        @if ($document->canBeSigned())
            <section class="ghl-card">
                <h2>Sign</h2>
                <form method="post" action="{{ route('documents.sign', $document->token) }}" class="ghl-stack">
                    @csrf
                    <input class="ghl-input ghl-signature" type="text" name="signer_name" placeholder="Type your full name" value="{{ old('signer_name', $document->contact?->full_name) }}" required maxlength="120">
                    <label class="ghl-radio"><input type="checkbox" name="agree" value="1" required> I agree that typing my name is my electronic signature.</label>
                    @if ($errors->any())<p class="ghl-error">{{ $errors->first() }}</p>@endif
                    <div class="ghl-row">
                        <button class="ghl-button" type="submit" name="decision" value="sign">Sign document</button>
                        <button class="ghl-button ghl-button--secondary" type="submit" name="decision" value="decline" formnovalidate onclick="this.form.agree.required=false; this.form.signer_name.required=false; this.form.agree.checked=true; if(!this.form.signer_name.value) this.form.signer_name.value='Declined';">Decline</button>
                    </div>
                </form>
            </section>
        @endif
    </main>
</x-layouts.public>
