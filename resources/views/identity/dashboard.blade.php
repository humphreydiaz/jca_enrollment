<x-layouts.app title="My account">
    <div class="mx-auto max-w-3xl space-y-6">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="dashboard-title">
            <x-ui.status-badge tone="ready">Secure access active</x-ui.status-badge>
            <h1 id="dashboard-title" class="mt-4 text-3xl font-black text-brand-navy-900">Welcome, {{ auth()->user()->name }}</h1>
            <p class="mt-3 leading-7 text-muted">Your account and access scope are ready. Enrollment, academic, billing, and communication modules will be added in their planned phases.</p>
        </section>

        <section class="jca-card p-6" aria-labelledby="account-security-title">
            <h2 id="account-security-title" class="text-lg font-black text-brand-navy-900">Account security</h2>
            <a href="{{ route('identity.password.edit') }}" class="jca-button jca-button-secondary mt-4">Change password</a>
        </section>
    </div>
</x-layouts.app>
