<x-layouts.public :title="$page->seo_title ?: $page->title" :description="$page->seo_description">
    @push('head')
        <x-page-builder::styles />
    @endpush
    <main class="ghl-site-page">
        <x-page-builder::blocks :blocks="$page->getBlocks()" panel="app" />
    </main>
</x-layouts.public>
