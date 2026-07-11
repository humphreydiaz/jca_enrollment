<x-layouts.public title="Invitation unavailable">
    <div class="mx-auto max-w-lg">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="invalid-invitation-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-red-600">Invitation unavailable</p>
            <h1 id="invalid-invitation-title" class="mt-2 text-3xl font-black text-brand-navy-900">This setup link cannot be used.</h1>
            <p class="mt-4 leading-7 text-muted">It may have expired, already been used, or been replaced. Contact the school if you need another invitation.</p>
            <a href="{{ route('identity.login') }}" class="jca-button jca-button-secondary mt-6">Return to sign in</a>
        </section>
    </div>
</x-layouts.public>
