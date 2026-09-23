<x-layouts.app :title="$announcement->title">
    @php $isAdmin = request()->user()?->hasRole('admin') ?? false; @endphp

    <div class="mx-auto max-w-3xl space-y-5">
        <a href="{{ route('communications.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-muted transition hover:text-brand-navy-900">
            <x-ui.icon name="arrow-right" size="size-4" class="rotate-180" />
            All announcements
        </a>

        <article class="jca-card overflow-hidden p-0">
            <header class="border-b border-border bg-gradient-to-br from-white to-slate-50/80 p-6 sm:p-8">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">
                        <x-ui.icon name="announcement" size="size-4" />
                        School announcement
                    </span>
                    @if ($isAdmin)
                        <x-ui.status-badge :status="$announcement->status" />
                    @endif
                </div>

                <h1 class="mt-3 text-2xl font-black leading-tight tracking-tight text-brand-navy-900 sm:text-3xl">{{ $announcement->title }}</h1>

                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="progress" size="size-4" class="text-slate-400" />
                        @if ($announcement->published_at)
                            Published {{ $announcement->published_at->timezone(config('app.timezone'))->format('M j, Y · g:i A') }}
                        @else
                            Saved draft — not yet published
                        @endif
                    </span>
                    @if ($isAdmin && $announcement->author)
                        <span class="hidden text-slate-300 sm:inline">•</span>
                        <span class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="people" size="size-4" class="text-slate-400" />
                            {{ $announcement->author->name }}
                        </span>
                    @endif
                </div>
            </header>

            <div class="p-6 sm:p-8">
                <div class="whitespace-pre-line text-base leading-7 text-text">{{ $announcement->body }}</div>

                @if ($isAdmin && $announcement->audiences->isNotEmpty())
                    <div class="mt-8 border-t border-border pt-5">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">Audience</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($announcement->audiences as $audience)
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $audience->label() }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </article>
    </div>
</x-layouts.app>
