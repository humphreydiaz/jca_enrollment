<x-layouts.app title="Communications">
    <div class="mx-auto max-w-6xl space-y-6" data-preserve-scroll data-ajax-config data-communications>
        @php
            $smsEnabled = (bool) config('communications.sms.enabled');
            $smsDriver = (string) config('communications.sms.driver');
            $smsLive = $smsEnabled && $smsDriver === 'semaphore';
            $smsTest = $smsEnabled && $smsDriver === 'log';
            $smsSender = (string) config('communications.sms.semaphore.sender_name');
            $routeLabel = $route === 'standard' ? 'standard queue (1 credit per 160-character part)' : 'priority queue (2 credits per 160-character part)';
        @endphp

        <x-ui.page-header title="Communications" description="Post announcements on the portal, by text message, or both, and text specific people directly." eyebrow="Admin workspace" />

        @if ($smsLive)
            <div class="flex gap-3 rounded-xl border border-success-700 bg-success-50 p-4 text-success-700" role="status">
                <x-ui.icon name="check" class="mt-0.5" />
                <div>
                    <p class="font-bold">Live SMS is active</p>
                    <p class="mt-1 text-sm leading-6">Texts are sent from &ldquo;{{ $smsSender }}&rdquo; on Semaphore's {{ $routeLabel }}. Announcement texts reach only people who <strong>opted in</strong>; texts to people you pick under Message people always go out. Live sending uses real credits.</p>
                </div>
            </div>
        @elseif ($smsTest)
            <div class="flex gap-3 rounded-xl border border-brand-orange-500 bg-warning-50 p-4 text-warning-800" role="note">
                <x-ui.icon name="warning" class="mt-0.5" />
                <div>
                    <p class="font-bold">SMS is in test mode</p>
                    <p class="mt-1 text-sm leading-6">Texts are recorded but <strong>not delivered</strong> to any phone.</p>
                </div>
            </div>
        @else
            <div class="flex gap-3 rounded-xl border border-brand-orange-500 bg-warning-50 p-4 text-warning-800" role="note">
                <x-ui.icon name="warning" class="mt-0.5" />
                <div>
                    <p class="font-bold">Live SMS is disabled</p>
                    <p class="mt-1 text-sm leading-6">Portal announcements work normally. Texts are recorded as not sent until SMS is enabled with a production sender.</p>
                </div>
            </div>
        @endif

        <div class="grid items-start gap-6 xl:grid-cols-2">
            @include('communications.admin.partials.announcement-composer')
            @include('communications.admin.partials.direct-message')
        </div>

        @include('communications.admin.partials.history')
    </div>

    {{-- Announcement preview modal (loaded over AJAX from communications.preview) --}}
    <dialog id="announcement-preview" class="m-auto w-[min(40rem,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55">
        <div class="flex items-start justify-between gap-4 border-b border-border bg-gradient-to-br from-white to-slate-50/80 p-5 sm:p-6">
            <div class="min-w-0">
                <p class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">
                    <x-ui.icon name="announcement" size="size-4" /> <span data-preview-delivery>School announcement</span>
                </p>
                <h2 data-preview-title class="mt-2 text-xl font-black leading-tight text-brand-navy-900 sm:text-2xl">&nbsp;</h2>
            </div>
            <button type="button" data-preview-close class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-brand-navy-900" aria-label="Close">
                <x-ui.icon name="close" />
            </button>
        </div>

        <div class="p-5 sm:p-6">
            <div data-preview-loading class="hidden py-8 text-center text-sm text-muted">Loading announcement…</div>
            <div data-preview-error class="hidden rounded-lg border border-brand-red-600 bg-red-50 p-4 text-sm font-semibold text-brand-red-600"></div>
            <div data-preview-content class="hidden">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
                    <span data-preview-status></span>
                    <span data-preview-date class="inline-flex items-center gap-1.5"></span>
                    <span data-preview-author class="inline-flex items-center gap-1.5"></span>
                </div>
                <div data-preview-body class="mt-5 whitespace-pre-line text-base leading-7 text-text"></div>
                <div data-preview-sms-wrap class="mt-5 hidden rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">Text message</p>
                    <p data-preview-sms class="mt-2 whitespace-pre-line text-sm leading-6 text-text"></p>
                </div>
                <div data-preview-audience-wrap class="mt-6 hidden border-t border-border pt-5">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">Audience</p>
                    <div data-preview-audiences class="mt-2 flex flex-wrap gap-2"></div>
                </div>
                <div class="mt-6 flex justify-end">
                    <a data-preview-link href="#" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand-navy-700 hover:underline">
                        Open portal page <x-ui.icon name="arrow-right" size="size-4" />
                    </a>
                </div>
            </div>
        </div>
    </dialog>
</x-layouts.app>
