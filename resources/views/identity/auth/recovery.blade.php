<x-layouts.public title="Account recovery">
    <div class="mx-auto max-w-lg">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="recovery-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Account recovery</p>
            <h1 id="recovery-title" class="mt-2 text-3xl font-black text-brand-navy-900">Need help signing in?</h1>
            <p class="mt-4 leading-7 text-muted">For privacy, JCA does not provide public password-reset requests at this time. Please contact the school registrar or designated account administrator for identity-verified assistance.</p>
            <a href="{{ route('identity.login') }}" class="jca-button jca-button-secondary mt-6">Return to sign in</a>
        </section>
    </div>
</x-layouts.public>
