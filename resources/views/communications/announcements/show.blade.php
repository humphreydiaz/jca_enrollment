<x-layouts.app :title="$announcement->title">
    <article class="mx-auto max-w-3xl jca-card p-6 sm:p-8"><p class="text-sm font-bold text-muted">School announcement</p><h1 class="mt-2 text-3xl font-black text-brand-navy-900">{{ $announcement->title }}</h1><p class="mt-6 whitespace-pre-line leading-7 text-text">{{ $announcement->body }}</p><p class="mt-6 text-sm text-muted">Published {{ $announcement->published_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p></article>
</x-layouts.app>
