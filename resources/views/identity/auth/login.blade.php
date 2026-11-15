<x-layouts.public title="Sign in">
    <section class="jca-card mx-auto grid max-w-5xl overflow-hidden lg:grid-cols-[minmax(0,0.82fr)_minmax(0,1fr)]" aria-labelledby="login-title">
        <div class="flex flex-col justify-center p-6 sm:p-10 lg:p-12">
            <div class="lg:hidden">
                <x-brand-mark />
            </div>

            <p class="mt-8 text-xs font-extrabold uppercase tracking-[0.2em] text-brand-orange-500 lg:mt-0">JCA secure portal</p>
            <h1 id="login-title" class="mt-2 text-3xl font-black tracking-tight text-brand-navy-900 sm:text-4xl">Welcome back</h1>
            <p class="mt-3 max-w-md text-sm leading-6 text-muted">
                Students use their <strong>Student ID</strong>, staff use their <strong>Staff ID</strong>, and parents or guardians use the Student ID issued for the child they are authorized to manage.
            </p>

            <x-identity.login-form :action="route('identity.login.store')" />

        </div>

        <div class="relative order-first min-h-56 overflow-hidden bg-brand-navy-900 lg:order-last lg:min-h-[36rem]">
            <img
                src="{{ asset('images/logo/JCA-Parent-Student-Login-Image.png') }}"
                alt="JCSGO Christian Academy campus, learners, and school seal"
                class="absolute inset-0 size-full object-cover"
            >
            <div class="absolute inset-0 bg-gradient-to-t from-brand-navy-900/25 via-transparent to-transparent" aria-hidden="true"></div>
        </div>
    </section>
</x-layouts.public>
