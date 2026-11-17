{{-- D-070: SMS status and a test text, so the Admin can confirm live texting works. The API key is never shown. --}}
@php($s = $smsStatus)
<section class="jca-card p-5 sm:p-6" aria-labelledby="sms-status-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 id="sms-status-title" class="text-lg font-black text-brand-navy-900">SMS status and test</h2>
        <span @class([
            'rounded-full px-3 py-1 text-xs font-extrabold',
            'bg-success-50 text-success-700' => $s['mode'] === 'live',
            'bg-warning-100 text-warning-800' => $s['mode'] !== 'live',
        ])>{{ match ($s['mode']) { 'live' => 'Live', 'test' => 'Test mode (not delivered)', 'missing_key' => 'API key missing', default => 'Off' } }}</span>
    </div>

    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-canvas p-3"><dt class="text-xs font-bold uppercase tracking-wider text-muted">Semaphore API key</dt><dd class="mt-1 font-semibold text-ink">{{ $s['key_set'] ? 'Set (hidden)' : 'Not set' }}</dd></div>
        <div class="rounded-lg bg-canvas p-3"><dt class="text-xs font-bold uppercase tracking-wider text-muted">Sender name</dt><dd class="mt-1 font-semibold text-ink">{{ $s['sender'] ?? 'Semaphore default (no approved name yet)' }}</dd></div>
        <div class="rounded-lg bg-canvas p-3"><dt class="text-xs font-bold uppercase tracking-wider text-muted">Credit balance</dt><dd class="mt-1 font-semibold tabular-nums text-ink">{{ $s['mode'] === 'live' ? ($s['balance'] ?? 'Could not be read') : '—' }}</dd></div>
        <div class="rounded-lg bg-canvas p-3"><dt class="text-xs font-bold uppercase tracking-wider text-muted">Sending queue</dt>
            <dd class="mt-1 font-semibold {{ $s['queue_stalled'] ? 'text-brand-red-600' : 'text-ink' }}">
                {{ $s['pending_jobs'] }} waiting{{ $s['failed_jobs'] > 0 ? ' · '.$s['failed_jobs'].' failed' : '' }}
                @if ($s['queue_stalled'])<span class="block text-xs">Texts have waited over 10 minutes: the queue cron job is not running.</span>@endif
            </dd>
        </div>
    </dl>
    @if ($s['last_sent_at'] || $s['last_failure'])
        <p class="mt-3 text-xs text-muted">
            {{ $s['last_sent_at'] ? 'Last text accepted: '.\Illuminate\Support\Carbon::parse($s['last_sent_at'])->timezone('Asia/Manila')->format('d M Y, g:i A').'.' : '' }}
            {{ $s['last_failure'] ? ' Last failure: '.$s['last_failure'].'.' : '' }}
        </p>
    @endif

    <form method="POST" action="{{ route('communications.sms-test') }}" class="mt-5 flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label for="test_mobile" class="jca-label">Send a test text to</label>
            <input id="test_mobile" name="test_mobile" type="tel" inputmode="tel" maxlength="13" value="{{ old('test_mobile') }}" placeholder="09XXXXXXXXX" class="jca-input mt-1" required>
        </div>
        <x-ui.button type="submit" variant="secondary">Send test text</x-ui.button>
        <p class="w-full text-xs text-muted">Sends one short fixed message using real credits. The number is not saved; the audit log keeps it masked.</p>
    </form>

    @if ($s['mode'] !== 'live')
        <details class="mt-4 text-sm">
            <summary class="cursor-pointer font-bold text-brand-navy-700">How to turn on live SMS</summary>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-ink">
                <li>In the server's <code>.env</code> set <code>SMS_ENABLED=true</code> and <code>SMS_DRIVER=semaphore</code>.</li>
                <li>Paste your Semaphore API key after <code>SEMAPHORE_API_KEY=</code>. Add <code>SEMAPHORE_SENDER_NAME=</code> only once Semaphore approves the school's sender name.</li>
                <li>Run <code>php artisan config:cache</code>, then make sure the hPanel cron job runs <code>queue:work</code> every minute.</li>
                <li>Come back here and send a test text.</li>
            </ol>
        </details>
    @endif
</section>
