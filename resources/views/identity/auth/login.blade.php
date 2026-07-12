<x-layouts.public title="Sign in">
    <div class="mx-auto max-w-md">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="login-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Secure access</p>
            <h1 id="login-title" class="mt-2 text-3xl font-black text-brand-navy-900">Sign in to JCA</h1>
            <p class="mt-3 text-sm leading-6 text-muted">Use the email address or mobile number connected to your school account.</p>

            <form method="POST" action="{{ route('identity.login.store') }}" class="mt-6 space-y-5">
                @csrf

                <x-ui.field for="identifier" label="Email address or mobile number">
                    <input id="identifier" name="identifier" value="{{ old('identifier') }}" autocomplete="username" required class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm" />
                </x-ui.field>

                <x-ui.field for="password" label="Password">
                    <div x-data="{ visible: false }" class="relative">
                        <input id="password" name="password" :type="visible ? 'text' : 'password'" autocomplete="current-password" required class="block w-full rounded-lg border-border bg-white px-3 py-3 pr-20 text-ink shadow-sm" />
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-sm font-bold text-brand-navy-700" @click="visible = !visible" :aria-pressed="visible.toString()">Show</button>
                    </div>
                </x-ui.field>

                <x-ui.button type="submit" class="w-full">Sign in</x-ui.button>
            </form>

            <p class="mt-6 text-center text-sm text-muted"><a href="{{ route('identity.recovery') }}" class="font-bold text-brand-navy-700 underline">Need help signing in?</a></p>
        </section>
    </div>
</x-layouts.public>
