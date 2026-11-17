@props([
    'action',
    'submitLabel' => 'Sign in',
    'identifierLabel' => 'Username',
    'identifierPlaceholder' => 'e.g. JCA-26-693 or 26-JCA-783',
])

<form method="POST" action="{{ $action }}" class="mt-7 space-y-5">
    @csrf

    <x-ui.field for="identifier" :label="$identifierLabel">
        <input
            id="identifier"
            name="identifier"
            type="text"
            value="{{ old('identifier') }}"
            autocomplete="username"
            @if($identifierPlaceholder) placeholder="{{ $identifierPlaceholder }}" @endif
            required
            autofocus
            class="jca-input mt-0"
        />
    </x-ui.field>

    <x-ui.field for="password" label="Password">
        <div x-data="{ visible: false }" class="relative">
            <input
                id="password"
                name="password"
                :type="visible ? 'text' : 'password'"
                autocomplete="current-password"
                required
                class="jca-input mt-0 pr-20"
            />
            <button
                type="button"
                class="absolute inset-y-0 right-0 min-w-16 px-3 text-sm font-bold text-brand-navy-700"
                @click="visible = !visible"
                :aria-pressed="visible.toString()"
                :aria-label="visible ? 'Hide password' : 'Show password'"
                x-text="visible ? 'Hide' : 'Show'"
            >Show</button>
        </div>
    </x-ui.field>

    <div class="flex justify-end">
        <a href="{{ route('identity.recovery') }}" class="text-sm font-bold text-brand-navy-700 underline decoration-brand-navy-700/30 underline-offset-4 hover:text-brand-navy-900">
            Need help signing in?
        </a>
    </div>

    <x-ui.button type="submit" class="w-full">{{ $submitLabel }}</x-ui.button>

    {{-- Staff onboarding/recovery only: the Admin issues a one-time code (it may arrive by SMS without a link). Families use the Registrar's temporary password. --}}
    <p class="border-t border-border pt-4 text-center text-xs text-muted">
        School staff with a one-time account code?
        <a href="{{ route('identity.invitations.activate') }}" class="font-bold text-brand-navy-700 underline decoration-brand-navy-700/30 underline-offset-4 hover:text-brand-navy-900">Set up your staff account</a>
    </p>
</form>
