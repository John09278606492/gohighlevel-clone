<header class="ghl-doc__brand">
    @if ($logo = $account->getFilamentAvatarUrl())
        <img src="{{ $logo }}" alt="" class="ghl-doc__logo">
    @endif
    <div>
        <p class="ghl-doc__business">{{ $account->name }}</p>
        @if ($account->email || $account->phone)
            <p class="ghl-doc__muted">{{ collect([$account->email, $account->phone])->filter()->implode(' · ') }}</p>
        @endif
    </div>
</header>
