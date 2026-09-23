<section data-section="announcements" class="jca-card p-5 sm:p-6" aria-labelledby="posted-announcements-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="posted-announcements-title" class="text-xl font-black text-brand-navy-900">Posted announcements</h2>
            <p class="mt-1 text-sm text-muted">Every announcement created through this workspace, newest first. Drafts are visible only to admins until published.</p>
        </div>
        <x-ui.status-badge tone="neutral" :label="$announcements->total().' total'" />
    </div>

    @if ($announcements->isNotEmpty())
        <div class="mt-4 overflow-x-auto rounded-xl border border-border">
            <table class="min-w-full divide-y divide-border text-left text-sm">
                <thead class="bg-canvas text-muted">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-bold sm:px-6">Announcement</th>
                        <th scope="col" class="hidden px-5 py-3 font-bold md:table-cell sm:px-6">Audience</th>
                        <th scope="col" class="px-5 py-3 font-bold sm:px-6">Status</th>
                        <th scope="col" class="hidden px-5 py-3 font-bold lg:table-cell sm:px-6">Date</th>
                        <th scope="col" class="hidden px-5 py-3 font-bold xl:table-cell sm:px-6">Author</th>
                        <th scope="col" class="px-5 py-3 text-right font-bold sm:px-6">View</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border bg-white">
                    @foreach ($announcements as $announcement)
                        <tr class="hover:bg-slate-50/70">
                            <td class="max-w-xs px-5 py-4 sm:px-6">
                                <p class="truncate font-bold text-brand-navy-900">{{ $announcement->title }}</p>
                                <p class="mt-0.5 truncate text-xs text-muted">{{ \Illuminate\Support\Str::limit($announcement->body, 80) }}</p>
                            </td>
                            <td class="hidden px-5 py-4 md:table-cell sm:px-6">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($announcement->audiences->take(2) as $audience)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $audience->label() }}</span>
                                    @empty
                                        <span class="text-xs text-muted">—</span>
                                    @endforelse
                                    @if ($announcement->audiences->count() > 2)
                                        <span class="text-xs font-semibold text-slate-400">+{{ $announcement->audiences->count() - 2 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 sm:px-6"><x-ui.status-badge :status="$announcement->status" /></td>
                            <td class="hidden whitespace-nowrap px-5 py-4 text-muted lg:table-cell sm:px-6">
                                {{ $announcement->published_at?->timezone(config('app.timezone'))->format('M j, Y') ?? 'Draft' }}
                            </td>
                            <td class="hidden whitespace-nowrap px-5 py-4 text-muted xl:table-cell sm:px-6">{{ $announcement->author?->name ?? '—' }}</td>
                            <td class="px-5 py-4 text-right sm:px-6">
                                <div class="flex items-center justify-end gap-3">
                                    @if ($announcement->status === 'draft')
                                        <button type="button"
                                            data-publish-url="{{ route('communications.publish', $announcement) }}"
                                            data-publish-title="{{ $announcement->title }}"
                                            class="inline-flex items-center gap-1 font-bold text-success-700 hover:underline">
                                            <x-ui.icon name="check" size="size-4" /> Publish
                                        </button>
                                    @endif
                                    <button type="button" data-preview-url="{{ route('communications.preview', $announcement) }}" class="inline-flex items-center gap-1 font-bold text-brand-orange-500 hover:underline">
                                        Open<x-ui.icon name="arrow-right" size="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($announcements->hasPages())
            <div class="mt-4">{{ $announcements->links() }}</div>
        @endif
    @else
        <div class="mt-4">
            <x-ui.empty-state icon="announcement" title="No announcements yet" description="Announcements you create above will be listed here with their audience and publication status." />
        </div>
    @endif
</section>
