<x-layouts.public title="Staff account setup">
    <div class="mx-auto max-w-md">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="activation-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Staff account setup</p>
            <h1 id="activation-title" class="mt-2 text-3xl font-black text-brand-navy-900">Enter your account code</h1>
            <p class="mt-3 text-sm leading-6 text-muted">For school staff setting up a new account or recovering one: enter the one-time code from the school administrator. Spaces and dashes are accepted.</p>
            <p class="mt-3 rounded-lg bg-canvas p-3 text-sm leading-6 text-muted">Parents, guardians, and students don’t need a code. Sign in with the Student ID and the temporary password given by the Registrar.</p>

            <form method="POST" action="{{ route('identity.invitations.activate.store') }}" class="mt-6 space-y-5">
                @csrf
                <x-ui.field for="code" label="Account code">
                    <input
                        id="code"
                        name="code"
                        type="text"
                        value="{{ old('code') }}"
                        inputmode="text"
                        autocomplete="one-time-code"
                        autocapitalize="characters"
                        required
                        autofocus
                        placeholder="ABCD-EFGH-JKLM-NPQR"
                        class="jca-input mt-0 font-mono uppercase tracking-wider"
                    />
                </x-ui.field>
                <x-ui.button type="submit" class="w-full">Continue to password setup</x-ui.button>
            </form>

            <p class="mt-6 text-center text-sm text-muted">
                Already activated?
                <a href="{{ route('identity.login') }}" class="font-bold text-brand-navy-700 underline decoration-brand-navy-700/30 underline-offset-4">Return to sign in</a>
            </p>
        </section>
    </div>
</x-layouts.public>
