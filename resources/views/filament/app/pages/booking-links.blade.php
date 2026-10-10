<div class="space-y-3">
    @forelse ($calendars as $calendar)
        <div class="ghl-link-row">
            <div>
                <div class="font-medium">{{ $calendar->name }}</div>
                <a href="{{ $calendar->bookingUrl() }}" target="_blank" class="text-sm text-primary-600 break-all">{{ $calendar->bookingUrl() }}</a>
            </div>
        </div>
    @empty
        <p>No active calendars yet.</p>
    @endforelse
</div>
