<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin:24px 0">
    @foreach ($reviews as $review)
        <figure style="margin:0;padding:20px;border-radius:14px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08);border:1px solid #eee">
            <div style="color:#f59e0b;font-size:18px;letter-spacing:2px" aria-label="{{ $review->rating }} stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
            <blockquote style="margin:10px 0;color:#374151">“{{ $review->comment }}”</blockquote>
            <figcaption style="font-size:14px;color:#6b7280">{{ $review->author_name ?: 'Verified customer' }}</figcaption>
        </figure>
    @endforeach
</div>
