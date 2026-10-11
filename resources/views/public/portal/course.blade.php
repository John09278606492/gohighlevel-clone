@php($account = $course->subAccount)
@php($progress = $enrollment->progress())
<x-layouts.public :title="$course->title.' · '.$account->name">
    <main class="ghl-doc">
        @include('public.partials.brand', ['account' => $account])

        <section class="ghl-card">
            <p class="ghl-doc__muted">Welcome back, {{ $enrollment->contact->first_name ?: 'there' }}</p>
            <h1>{{ $course->title }}</h1>
            @if ($course->description)<p>{{ $course->description }}</p>@endif
            <div class="ghl-progress" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                <div style="width: {{ $progress }}%"></div>
            </div>
            <p class="ghl-doc__muted">{{ $progress }}% complete · {{ count($completed) }} of {{ $course->lessons->count() }} lessons</p>
        </section>

        @foreach ($course->lessons->groupBy(fn ($l) => $l->module ?: 'Lessons') as $module => $lessons)
            <section class="ghl-card">
                <h2>{{ $module }}</h2>
                <ol class="ghl-lessons">
                    @foreach ($lessons as $lesson)
                        <li @class(['is-done' => in_array($lesson->id, $completed)])>
                            <a href="{{ route('portal.lesson', [$enrollment->token, $lesson]) }}">
                                <span class="ghl-lessons__check">{{ in_array($lesson->id, $completed) ? '✓' : '' }}</span>
                                {{ $lesson->title }}
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </main>
</x-layouts.public>
