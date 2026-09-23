<x-layouts.public title="Staff sign in">
    <div class="mx-auto max-w-lg">
        <section class="jca-card overflow-hidden" aria-labelledby="staff-login-title">
            <div class="border-b border-border bg-brand-navy-900 px-6 py-7 text-white sm:px-9">
                <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-brand-gold-500">JCA staff workspace</p>
                <h1 id="staff-login-title" class="mt-2 text-3xl font-black tracking-tight">Staff sign in</h1>
                <p class="mt-3 text-sm leading-6 text-slate-200">
                    For school administrators, registrars, accounting staff, and teachers.
                </p>
            </div>

            <div class="p-6 sm:p-9">
                <p class="text-sm leading-6 text-muted">
                    Use the email address or mobile number assigned to your staff account.
                </p>

                <x-identity.login-form :action="route('identity.staff.login.store')" submit-label="Sign in to staff workspace" identifier-label="Email address or mobile number" :identifier-placeholder="null" />

                <div class="mt-8 border-t border-border pt-5 text-center text-sm text-muted">
                    Parent, guardian, or student?
                    <a href="{{ route('identity.login') }}" class="ml-1 font-bold text-brand-navy-700 underline decoration-brand-navy-700/30 underline-offset-4 hover:text-brand-navy-900">Parent and student sign in</a>
                </div>
            </div>
        </section>
    </div>
</x-layouts.public>
