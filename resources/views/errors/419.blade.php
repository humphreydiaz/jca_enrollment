<x-layouts.public title="Session expired">
    <main class="mx-auto grid min-h-[70vh] max-w-2xl place-items-center px-4 py-12">
        <section class="jca-card w-full p-6 text-center sm:p-10" role="alert" aria-labelledby="session-expired-title">
            <span class="mx-auto grid size-12 place-items-center rounded-xl bg-amber-50 text-amber-700"><x-ui.icon name="warning" /></span>
            <p class="mt-5 text-sm font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Secure session ended</p>
            <h1 id="session-expired-title" class="mt-2 text-3xl font-black text-brand-navy-900">Please sign in again</h1>
            <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-muted">The page or form was open longer than the secure session, or another account signed in within the same browser session. No enrollment change was saved from that expired request.</p>
            <a class="jca-button-primary mt-6" href="{{ route('identity.login') }}">Return to sign in</a>
        </section>
    </main>
</x-layouts.public>
