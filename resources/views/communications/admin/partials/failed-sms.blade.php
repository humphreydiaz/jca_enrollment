<section data-section="failed-sms" class="jca-card p-5 sm:p-6" aria-labelledby="failed-sms-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 id="failed-sms-title" class="text-xl font-black text-brand-navy-900">Failed SMS deliveries</h2><p class="mt-1 text-sm text-muted">Retrying queues the existing notification; it does not create a duplicate notification.</p></div>
        <x-ui.status-badge :status="$failedNotifications->isEmpty() ? 'clear' : 'failed'" :label="$failedNotifications->isEmpty() ? 'Queue clear' : $failedNotifications->count().' failed'" />
    </div>
    <div class="mt-4 space-y-3">
        @forelse($failedNotifications as $notification)
            <article class="flex flex-col justify-between gap-4 rounded-xl border border-border p-4 sm:flex-row sm:items-center">
                <div><p class="font-bold text-brand-navy-900">{{ str($notification->purpose)->headline() }}</p><p class="mt-1 text-sm text-muted">Attempted {{ $notification->message?->attempt_count ?? 0 }} time(s); failure code: {{ $notification->message?->failure_code ?? 'unavailable' }}</p></div>
                <form method="POST" action="{{ route('communications.notifications.retry', $notification) }}" data-ajax>@csrf<x-ui.button variant="secondary" type="submit">Retry safely</x-ui.button></form>
            </article>
        @empty
            <x-ui.empty-state title="No failed SMS deliveries" description="Failed queued messages will appear here with a safe retry action." icon="communications" />
        @endforelse
    </div>
</section>
