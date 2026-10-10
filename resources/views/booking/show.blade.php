<x-layouts.public :title="$calendar->name.' · '.$calendar->subAccount->name" :body-class="$embed ? 'ghl-embed' : ''">
    <main class="ghl-booking-page">
        @unless ($embed)
            <header class="ghl-booking-page__header">
                @if ($logo = $calendar->subAccount->getFilamentAvatarUrl())
                    <img src="{{ $logo }}" alt="" class="ghl-booking-page__logo">
                @endif
                <p class="ghl-booking-page__business">{{ $calendar->subAccount->name }}</p>
                <h1>{{ $calendar->name }}</h1>
                <p class="ghl-booking-page__meta">{{ $calendar->duration_minutes }} minutes · {{ $calendar->timezone }}</p>
                @if ($calendar->description)
                    <p class="ghl-booking-page__desc">{{ $calendar->description }}</p>
                @endif
            </header>
        @endunless

        <livewire:booking-widget :calendar="$calendar" />
    </main>
</x-layouts.public>
