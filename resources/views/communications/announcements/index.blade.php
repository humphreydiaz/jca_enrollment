<x-layouts.app title="Announcements">
    <div class="mx-auto max-w-4xl space-y-6">
        <header><h1 class="text-3xl font-black text-brand-navy-900">Announcements</h1><p class="mt-2 text-muted">School messages intended for your authorized portal audience.</p></header>
        @forelse ($announcements as $announcement)
            <article class="jca-card p-6"><h2 class="text-xl font-black text-brand-navy-900"><a class="underline" href="{{ route('communications.show', $announcement) }}">{{ $announcement->title }}</a></h2><p class="mt-3 whitespace-pre-line text-text">{{ \Illuminate\Support\Str::limit($announcement->body, 320) }}</p><p class="mt-4 text-sm text-muted">{{ $announcement->published_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? 'Saved draft' }}</p></article>
        @empty
            <p class="jca-card p-6 text-muted">There are no announcements available to your account.</p>
        @endforelse
        {{ $announcements->links() }}
    </div>
</x-layouts.app>
