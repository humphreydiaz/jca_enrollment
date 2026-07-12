<x-layouts.app title="SMS preferences">
    <div class="mx-auto max-w-2xl space-y-6"><header><h1 class="text-3xl font-black text-brand-navy-900">SMS preferences</h1><p class="mt-2 text-muted">Choose whether JCA may use the mobile number on your guardian profile for the message categories below.</p></header>
        <form class="jca-card space-y-5 p-6" method="POST" action="{{ route('communications.preferences.update') }}">@csrf @method('PUT')
            <p class="rounded-lg bg-brand-blue-100 p-4 text-sm text-brand-navy-900">Mobile contact: {{ $guardian->mobile ? 'ending in '.substr(preg_replace('/\D/', '', $guardian->mobile), -4) : 'not available' }}</p>
            @foreach (['transactional' => 'Enrollment updates', 'announcement' => 'General school announcements'] as $key => $label)
                <label class="flex items-start gap-3"><input class="mt-1" type="checkbox" name="{{ $key }}" value="1" @checked(($consents[$key]->status ?? 'withdrawn') === 'granted')><span><span class="font-bold text-text">{{ $label }}</span><span class="mt-1 block text-sm text-muted">{{ $key === 'transactional' ? 'Status updates without confidential detail.' : 'General school messages only.' }}</span></span></label>
            @endforeach
            <x-ui.button type="submit">Save preferences</x-ui.button>
        </form>
    </div>
</x-layouts.app>
