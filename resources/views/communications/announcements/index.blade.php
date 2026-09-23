<x-layouts.app title="Announcements">
    @php $isAdmin = request()->user()?->hasRole('admin') ?? false; @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        <x-ui.page-header
            eyebrow="School records"
            title="Announcements"
            description="School messages intended for your authorized portal audience."
        >
            @if ($announcements->total() > 0)
                <x-slot:actions>
                    <x-ui.status-badge tone="neutral" :label="$announcements->total().' total'" />
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        <div class="space-y-4">
            @forelse ($announcements as $announcement)
                @php $isDraft = $announcement->status === 'draft'; @endphp
                <article class="group jca-card relative p-5 transition duration-150 hover:-translate-y-0.5 hover:shadow-lg sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 ring-1 ring-brand-navy-700/10">
                                <x-ui.icon name="announcement" size="size-5" />
                            </span>
                            <h2 class="truncate text-lg font-black leading-tight text-brand-navy-900 sm:text-xl">
                                <a href="{{ route('communications.show', $announcement) }}" class="before:absolute before:inset-0 before:content-[''] group-hover:text-brand-navy-700">
                                    {{ $announcement->title }}
                                </a>
                            </h2>
                        </div>
                        @if ($isAdmin)
                            <x-ui.status-badge :status="$announcement->status" class="shrink-0" />
                        @endif
                    </div>

                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-text line-clamp-3">{{ \Illuminate\Support\Str::limit($announcement->body, 320) }}</p>

                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
                        <span class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="progress" size="size-4" class="text-slate-400" />
                            {{ $announcement->published_at?->timezone(config('app.timezone'))->format('M j, Y · g:i A') ?? 'Saved draft — not published' }}
                        </span>

                        @if ($isAdmin && $announcement->audiences->isNotEmpty())
                            <span class="hidden text-slate-300 sm:inline">•</span>
                            <span class="flex flex-wrap items-center gap-1.5">
                                @foreach ($announcement->audiences->take(3) as $audience)
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $audience->label() }}</span>
                                @endforeach
                                @if ($announcement->audiences->count() > 3)
                                    <span class="text-xs font-semibold text-slate-400">+{{ $announcement->audiences->count() - 3 }}</span>
                                @endif
                            </span>
                        @endif

                        <span class="ml-auto inline-flex items-center gap-1 font-bold text-brand-orange-500 opacity-0 transition group-hover:opacity-100">
                            Read <x-ui.icon name="arrow-right" size="size-4" />
                        </span>
                    </div>
                </article>
            @empty
                <x-ui.empty-state
                    icon="announcement"
                    title="No announcements yet"
                    description="There are no announcements available to your account. School messages you're authorized to see will appear here."
                />
            @endforelse
        </div>

        @if ($announcements->hasPages())
            <div>{{ $announcements->links() }}</div>
        @endif
    </div>
</x-layouts.app>
