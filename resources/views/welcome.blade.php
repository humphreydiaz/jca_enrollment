<x-layouts.public title="Welcome">
    <div class="grid items-center gap-8 lg:grid-cols-[1.15fr_0.85fr]">
        <section class="space-y-6">
            <x-ui.status-badge tone="ready">Secure foundation in progress</x-ui.status-badge>
            <div class="space-y-4">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">J.C.S.G.O. Christian Academy R1 Inc.</p>
                <h1 class="max-w-3xl text-4xl font-black tracking-tight text-brand-navy-900 sm:text-5xl">Enrollment made clearer for families and school staff.</h1>
                <p class="max-w-2xl text-lg leading-8 text-muted">JCA's enrollment and student management system is being prepared with private records, clear next steps, and a lively school-centered experience.</p>
            </div>
            <a class="jca-button jca-button-primary" href="{{ route('foundation.smoke') }}">View foundation status</a>
        </section>

        <aside class="jca-card overflow-hidden p-6 sm:p-8" aria-label="System foundation summary">
            <div class="rounded-2xl bg-brand-navy-900 p-6 text-white">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-brand-gold-500">Built for JCA</p>
                <p class="mt-3 text-2xl font-black">A light, accessible portal foundation.</p>
            </div>
            <dl class="mt-6 space-y-4">
                <div>
                    <dt class="text-sm font-bold text-brand-navy-900">Private by default</dt>
                    <dd class="mt-1 text-sm leading-6 text-muted">Sensitive documents will stay outside the public web root.</dd>
                </div>
                <div>
                    <dt class="text-sm font-bold text-brand-navy-900">Built for reliable records</dt>
                    <dd class="mt-1 text-sm leading-6 text-muted">MySQL, database queues, sessions, and cache support the planned modules.</dd>
                </div>
            </dl>
        </aside>
    </div>
</x-layouts.public>
