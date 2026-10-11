@php($account = $course->subAccount)
<x-layouts.public :title="$lesson->title.' · '.$course->title">
    <main class="ghl-doc">
        <p><a href="{{ route('portal.course', $enrollment->token) }}" class="ghl-link">← {{ $course->title }}</a></p>

        <article class="ghl-card ghl-prose">
            @if ($lesson->module)<p class="ghl-doc__muted">{{ $lesson->module }}</p>@endif
            <h1>{{ $lesson->title }}</h1>

            @if ($embed = $lesson->embedUrl())
                <div class="ghl-video"><iframe src="{{ $embed }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>
            @elseif ($lesson->video_url)
                <p><a class="ghl-link" href="{{ $lesson->video_url }}" rel="noopener" target="_blank">Watch the video</a></p>
            @endif

            {!! str((string) $lesson->content)->sanitizeHtml() !!}
        </article>

        <div class="ghl-row ghl-row--between">
            @if ($previous)
                <a class="ghl-button ghl-button--secondary" href="{{ route('portal.lesson', [$enrollment->token, $previous]) }}">Previous</a>
            @else
                <span></span>
            @endif

            <form method="post" action="{{ route('portal.complete', [$enrollment->token, $lesson]) }}">
                @csrf
                <button class="ghl-button" type="submit">
                    {{ in_array($lesson->id, $completed) ? ($next ? 'Next lesson' : 'Back to course') : ($next ? 'Complete and continue' : 'Complete course') }}
                </button>
            </form>
        </div>
    </main>
</x-layouts.public>
