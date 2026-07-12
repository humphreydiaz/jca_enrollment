<x-layouts.app title="Dashboard">
    <div class="mx-auto max-w-6xl space-y-8">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="dashboard-title">
            <x-ui.status-badge tone="ready">Secure role portal</x-ui.status-badge>
            <h1 id="dashboard-title" class="mt-4 text-3xl font-black text-brand-navy-900">Welcome, {{ auth()->user()->name }}</h1>
            <p class="mt-3 text-muted">Your next actions are limited to the records and responsibilities assigned to your account.</p>
        </section>
        <section aria-labelledby="next-actions-title">
            <h2 id="next-actions-title" class="text-xl font-black text-brand-navy-900">Next actions</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($cards as $card)
                    <a href="{{ $card['route'] }}" class="jca-card block p-5 transition hover:border-brand-navy-700 hover:shadow-md">
                        <span class="text-3xl font-black text-brand-navy-900">{{ $card['value'] }}</span>
                        <h3 class="mt-2 font-bold text-text">{{ $card['label'] }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $card['detail'] }}</p>
                    </a>
                @empty
                    <p class="jca-card p-5 text-muted">Your account does not yet have an active portal role.</p>
                @endforelse
            </div>
        </section>
        <section aria-labelledby="announcement-title">
            <div class="flex items-center justify-between gap-4"><h2 id="announcement-title" class="text-xl font-black text-brand-navy-900">Announcements</h2><a class="font-bold text-brand-navy-700 underline" href="{{ route('communications.index') }}">View all</a></div>
            <div class="mt-4 space-y-3">
                @forelse ($announcements as $announcement)
                    <a href="{{ route('communications.show', $announcement) }}" class="jca-card block p-5 hover:border-brand-navy-700"><h3 class="font-bold text-brand-navy-900">{{ $announcement->title }}</h3><p class="mt-2 text-sm text-muted">{{ \Illuminate\Support\Str::limit($announcement->body, 180) }}</p></a>
                @empty
                    <p class="jca-card p-5 text-muted">There are no announcements for your portal at this time.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
