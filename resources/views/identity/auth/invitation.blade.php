<x-layouts.public title="Set up your account">
    <div class="mx-auto max-w-md">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="invitation-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Account invitation</p>
            <h1 id="invitation-title" class="mt-2 text-3xl font-black text-brand-navy-900">Set your private password</h1>
            <p class="mt-3 text-sm leading-6 text-muted">This invitation is single-use and expires {{ $invitation->expires_at->timezone(config('app.timezone'))->format('d M Y, g:i A') }}.</p>

            <form method="POST" action="{{ route('identity.invitations.store', ['invitation' => $invitation->public_id, 'token' => $token]) }}" class="mt-6 space-y-5">
                @csrf
                <x-ui.field for="password" label="New password" hint="Use at least 12 characters with upper/lowercase letters and numbers.">
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm" />
                </x-ui.field>
                <x-ui.field for="password_confirmation" label="Confirm new password">
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm" />
                </x-ui.field>
                <x-ui.button type="submit" class="w-full">Activate account</x-ui.button>
            </form>
        </section>
    </div>
</x-layouts.public>
