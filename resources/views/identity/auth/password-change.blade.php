<x-layouts.app title="Change password">
    <div class="mx-auto max-w-lg">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="password-change-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Account security</p>
            <h1 id="password-change-title" class="mt-2 text-3xl font-black text-brand-navy-900">Change your password</h1>
            <p class="mt-3 text-sm leading-6 text-muted">Choose a private password. Saving this form signs out your other active sessions.</p>

            <form method="POST" action="{{ route('identity.password.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PUT')
                <x-ui.field for="current_password" label="Current password">
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm" />
                </x-ui.field>
                <x-ui.field for="password" label="New password" hint="Use at least 12 characters with upper/lowercase letters and numbers.">
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm" />
                </x-ui.field>
                <x-ui.field for="password_confirmation" label="Confirm new password">
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm" />
                </x-ui.field>
                <x-ui.button type="submit" class="w-full">Save new password</x-ui.button>
            </form>
        </section>
    </div>
</x-layouts.app>
