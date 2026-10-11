@php
    $activities = $getRecord()->activities()->with('user')->limit(50)->get();
@endphp
<div class="ghl-timeline">
    @forelse ($activities as $activity)
        @php
            [$label, $icon, $color] = \App\Models\Activity::TYPES[$activity->type] ?? [ucfirst($activity->type), 'heroicon-o-bolt', 'gray'];
        @endphp
        <div class="ghl-timeline__item">
            <div class="ghl-timeline__icon">
                <x-filament::icon :icon="$icon" @class(['h-4 w-4', 'text-gray-500' => $color === 'gray', 'text-primary-600' => $color === 'primary', 'text-success-600' => $color === 'success', 'text-warning-600' => $color === 'warning', 'text-danger-600' => $color === 'danger', 'text-info-600' => $color === 'info']) />
            </div>
            <div class="ghl-timeline__body">
                <div>{{ $activity->description }}</div>
                <div class="ghl-timeline__meta">
                    {{ $label }} · {{ $activity->created_at->diffForHumans() }}@if ($activity->user) · {{ $activity->user->name }}@endif
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">Nothing yet.</p>
    @endforelse
</div>
