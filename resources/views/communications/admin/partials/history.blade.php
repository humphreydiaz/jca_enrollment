{{-- D-052: everything sent from this page, with SMS delivery results and retry. --}}
<section data-section="history" class="jca-card" aria-labelledby="history-title" x-data="commsHistory(@js($tab ?? 'announcements'))">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b border-border px-5 py-4 sm:px-6">
        <div>
            <h2 id="history-title" class="text-xl font-black text-brand-navy-900">Sent history</h2>
            <p class="mt-1 text-sm text-muted">Newest first. Drafts are visible only to admins until published.</p>
        </div>
        <div class="flex gap-1 rounded-xl bg-slate-100 p-1 text-sm font-bold" role="tablist">
            <button type="button" role="tab" @click="tab = 'announcements'" :aria-selected="(tab === 'announcements').toString()" :class="tab === 'announcements' ? 'bg-white text-brand-navy-900 shadow-sm' : 'text-brand-navy-700'" class="rounded-lg px-3 py-1.5">Announcements <span class="text-muted">{{ $announcements->total() }}</span></button>
            <button type="button" role="tab" @click="tab = 'messages'" :aria-selected="(tab === 'messages').toString()" :class="tab === 'messages' ? 'bg-white text-brand-navy-900 shadow-sm' : 'text-brand-navy-700'" class="rounded-lg px-3 py-1.5">Direct messages <span class="text-muted">{{ $messages->total() }}</span></button>
        </div>
    </div>

    @php
        $smsSummary = function ($record): array {
            $parts = [];
            if ($record->sms_sent > 0) { $parts[] = ['tone' => 'text-success-700', 'text' => $record->sms_sent.' sent']; }
            if ($record->sms_waiting > 0) { $parts[] = ['tone' => 'text-muted', 'text' => $record->sms_waiting.' not sent yet']; }
            if ($record->sms_failed > 0) { $parts[] = ['tone' => 'text-brand-red-600', 'text' => $record->sms_failed.' failed']; }

            return $parts;
        };
    @endphp

    {{-- Announcements --}}
    <div x-show="tab === 'announcements'" @if (($tab ?? 'announcements') !== 'announcements') x-cloak @endif>
        @forelse ($announcements as $announcement)
            <article class="flex flex-col gap-3 border-b border-border px-5 py-4 last:border-b-0 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-bold text-brand-navy-900">{{ $announcement->title }}</p>
                        <x-ui.status-badge :status="$announcement->status" />
                        @if ($announcement->showsOnPortal())<span class="rounded-md bg-brand-blue-100 px-2 py-0.5 text-xs font-bold text-brand-navy-700">Portal</span>@endif
                        @if ($announcement->sendsSms())<span class="rounded-md bg-brand-orange-500/10 px-2 py-0.5 text-xs font-bold text-brand-orange-500">SMS</span>@endif
                    </div>
                    <p class="mt-1 line-clamp-1 text-sm text-muted">{{ $announcement->body }}</p>
                    <p class="mt-1 text-xs text-muted">
                        {{ $announcement->audiences->map->label()->join(', ') ?: '—' }}
                        · {{ $announcement->published_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? 'Draft' }}
                        @if ($announcement->author) · {{ $announcement->author->name }} @endif
                    </p>
                    @if ($announcement->sendsSms() && $announcement->status === 'published')
                        <p class="mt-1 text-xs font-semibold">
                            @forelse ($smsSummary($announcement) as $part)
                                <span class="{{ $part['tone'] }}">{{ $part['text'] }}</span>@unless ($loop->last) · @endunless
                            @empty
                                <span class="text-muted">No texts — no one in the audience opted in with a number</span>
                            @endforelse
                        </p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($announcement->status === 'draft')
                        <button type="button" class="jca-button-primary"
                            @click="publish(@js(route('communications.publish', $announcement)), @js(route('communications.draft-audience', $announcement)), @js($announcement->title))">Publish</button>
                    @endif
                    @if ($announcement->sms_failed > 0)
                        <form method="POST" action="{{ route('communications.retry-failed', ['announcement', $announcement->public_id]) }}" data-ajax>
                            @csrf<button type="submit" class="jca-button-secondary">Retry {{ $announcement->sms_failed }} failed</button>
                        </form>
                    @endif
                    <button type="button" data-preview-url="{{ route('communications.preview', $announcement) }}" class="jca-button-secondary">Open</button>
                </div>
            </article>
        @empty
            <div class="p-5 sm:p-6"><x-ui.empty-state icon="announcement" title="No announcements yet" description="Announcements you publish or save as drafts appear here." /></div>
        @endforelse
        @if ($announcements->hasPages())
            <div class="border-t border-border px-5 py-3 sm:px-6">{{ $announcements->links() }}</div>
        @endif
    </div>

    {{-- Direct messages --}}
    <div x-show="tab === 'messages'" @if (($tab ?? 'announcements') !== 'messages') x-cloak @endif>
        @forelse ($messages as $message)
            @php $names = $message->notifications->map(fn ($notification) => $notification->recipientName()); @endphp
            <article class="flex flex-col gap-3 border-b border-border px-5 py-4 last:border-b-0 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                <div class="min-w-0">
                    <p class="font-bold text-brand-navy-900">To {{ $names->take(3)->join(', ') }}@if ($names->count() > 3) and {{ $names->count() - 3 }} more @endif</p>
                    @php
    try {
        $safeBody = $message->body;
    } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
        $safeBody = 'Message unavailable (encryption key mismatch)';
    }
@endphp

<p class="mt-1 whitespace-pre-wrap break-words text-sm text-muted">{{ $safeBody }}</p>
                    <p class="mt-1 text-xs text-muted">{{ $message->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }} · {{ $message->sender?->name }}</p>
                    <p class="mt-1 text-xs font-semibold">
                        @foreach ($smsSummary($message) as $part)
                            <span class="{{ $part['tone'] }}">{{ $part['text'] }}</span>@unless ($loop->last) · @endunless
                        @endforeach
                    </p>
                    @if ($message->sms_failed > 0)
                        <p class="mt-1 text-xs text-brand-red-600">Failed: {{ $message->notifications->where('status', 'failed')->map(fn ($notification) => $notification->recipientName())->join(', ') }}</p>
                    @endif
                </div>
                @if ($message->sms_failed > 0)
                    <form method="POST" action="{{ route('communications.retry-failed', ['message', $message->public_id]) }}" data-ajax class="shrink-0">
                        @csrf<button type="submit" class="jca-button-secondary">Retry {{ $message->sms_failed }} failed</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="p-5 sm:p-6"><x-ui.empty-state icon="message" title="No direct messages yet" description="Texts you send under Message people appear here." /></div>
        @endforelse
        @if ($messages->hasPages())
            <div class="border-t border-border px-5 py-3 sm:px-6">{{ $messages->links() }}</div>
        @endif
    </div>

    {{-- Publish a draft: show who gets a text before anything is sent. --}}
    <dialog x-ref="publish" class="m-auto w-[min(30rem,calc(100%-2rem))] rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55">
        <form method="POST" :action="draft?.url" data-ajax>
            @csrf
            <div class="border-b border-border p-5">
                <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">Publish draft</p>
                <h3 class="mt-1 text-lg font-black text-brand-navy-900" x-text="draft?.title"></h3>
            </div>
            <div class="space-y-3 p-5 text-sm">
                <p x-show="draftLoading" class="text-muted">Counting recipients…</p>
                <p x-show="draftError" class="rounded-lg bg-warning-100 p-3 text-warning-800" x-text="draftError"></p>
                <template x-if="draft?.info">
                    <div class="space-y-2">
                        <p><span class="font-bold" x-text="draft.info.delivery"></span></p>
                        <template x-if="draft.info.sends_sms">
                            <p><strong class="text-xl font-black text-brand-navy-900" x-text="draft.info.total"></strong> <span x-text="draft.info.total === 1 ? 'person will get a text' : 'people will get a text'"></span> · about <strong x-text="draft.info.credits"></strong> credits</p>
                        </template>
                        <template x-if="! draft.info.sends_sms"><p class="text-muted">Portal only — no texts are sent.</p></template>
                    </div>
                </template>
            </div>
            <div class="flex justify-end gap-3 border-t border-border p-5">
                <button type="button" class="jca-button-secondary" @click="$refs.publish.close()">Cancel</button>
                <button type="submit" class="jca-button-primary" :disabled="draftLoading">Publish now</button>
            </div>
        </form>
    </dialog>
</section>
