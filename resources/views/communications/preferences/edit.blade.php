<x-layouts.app title="SMS preferences">
    <div class="mx-auto max-w-2xl space-y-6">
        <x-ui.page-header title="SMS preferences" description="Choose whether JCSGO Christian Academy may text you school announcements. Messages sent to you personally by the school office are not affected." eyebrow="My account" />

        <form class="jca-card space-y-5 p-6" method="POST" action="{{ route('communications.preferences.update') }}">
            @csrf @method('PUT')
            @forelse ($numbers as $key => $number)
                <div class="rounded-xl border border-border p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-muted">{{ $number['label'] }}</p>
                    @if ($number['mobile'])
                        <p class="mt-1 text-sm text-ink">Number on file: <span class="font-bold tabular-nums">{{ $number['mobile'] }}</span></p>
                        <label class="mt-3 flex items-start gap-3">
                            <input class="mt-1" type="checkbox" name="announcement[{{ $key }}]" value="1" @checked($number['granted'])>
                            <span><span class="font-bold text-text">Text me school announcements</span><span class="mt-0.5 block text-sm text-muted">General school news only. You can change this any time.</span></span>
                        </label>
                    @else
                        <p class="mt-1 text-sm text-muted">
                            No mobile number is on file, so no announcement texts can be sent.
                            @if ($key === 'staff')
                                Ask the school Admin to add your number to your staff account.
                            @else
                                Add it on your <a class="font-bold text-brand-navy-700 underline" href="{{ route('my-information.index') }}">Student Information</a> page, then come back here.
                            @endif
                        </p>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="message" title="No number on file" description="Ask the Registrar to add a mobile number to your record." />
            @endforelse
            <p class="text-xs text-muted">Parents and students change their number on the Student Information page; school staff ask the Admin.</p>
            <x-ui.button type="submit">Save preferences</x-ui.button>
        </form>
    </div>
</x-layouts.app>
