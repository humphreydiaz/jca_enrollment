<x-layouts.app title="User management">
    <div class="space-y-8">
        @if (session('invitation_url'))
            <section class="jca-card border-brand-gold-500 p-6" aria-labelledby="invitation-link-title">
                <h2 id="invitation-link-title" class="text-lg font-black text-brand-navy-900">Deliver this one-time invitation link securely</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Copy it now and send it only through the selected verified contact channel. It will not be shown again after this request.</p>
                <input readonly value="{{ session('invitation_url') }}" class="mt-4 block w-full rounded-lg border-border bg-canvas px-3 py-3 text-sm text-ink" aria-label="One-time invitation link" />
            </section>
        @endif

        <section class="jca-card p-6 sm:p-8" aria-labelledby="create-user-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Admin only</p>
            <h1 id="create-user-title" class="mt-2 text-3xl font-black text-brand-navy-900">Create an invited account</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-muted">Public registration is disabled. Create an account, assign compatible roles, and issue a single-use setup link.</p>

            <form method="POST" action="{{ route('identity.admin.users.store') }}" class="mt-6 grid gap-5 md:grid-cols-2">
                @csrf
                <x-ui.field for="name" label="Full name">
                    <input id="name" name="name" value="{{ old('name') }}" required class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm" />
                </x-ui.field>
                <x-ui.field for="channel" label="Invitation channel">
                    <select id="channel" name="channel" required class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm">
                        <option value="email" @selected(old('channel') === 'email')>Email</option>
                        <option value="mobile" @selected(old('channel') === 'mobile')>Mobile</option>
                    </select>
                </x-ui.field>
                <x-ui.field for="email" label="Email address">
                    <input id="email" name="email" value="{{ old('email') }}" type="email" autocomplete="email" class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm" />
                </x-ui.field>
                <x-ui.field for="mobile" label="Mobile number">
                    <input id="mobile" name="mobile" value="{{ old('mobile') }}" inputmode="tel" autocomplete="tel" class="block w-full rounded-lg border-border bg-white px-3 py-3 text-ink shadow-sm" />
                </x-ui.field>

                <fieldset class="md:col-span-2">
                    <legend class="text-sm font-bold text-ink">Roles</legend>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($roles as $role)
                            <label class="flex items-center gap-3 rounded-lg border border-border p-3 text-sm font-semibold text-ink">
                                <input type="checkbox" name="roles[]" value="{{ $role->code }}" @checked(in_array($role->code, old('roles', []), true)) class="size-4 rounded border-border text-brand-navy-700" />
                                {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('roles')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                </fieldset>

                <div class="md:col-span-2">
                    <x-ui.button type="submit">Create invitation</x-ui.button>
                </div>
            </form>
        </section>

        <section class="jca-card overflow-hidden" aria-labelledby="accounts-title">
            <div class="border-b border-border p-6">
                <h2 id="accounts-title" class="text-xl font-black text-brand-navy-900">Accounts</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-left text-sm">
                    <thead class="bg-canvas text-muted">
                        <tr>
                            <th class="px-6 py-3 font-bold">Name</th>
                            <th class="px-6 py-3 font-bold">Status</th>
                            <th class="px-6 py-3 font-bold">Roles</th>
                            <th class="px-6 py-3 font-bold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-white">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-bold text-ink">{{ $user->name }}</p>
                                    <p class="mt-1 text-xs text-muted">{{ $user->email ?? $user->mobile ?? 'No contact set' }}</p>
                                </td>
                                <td class="px-6 py-4"><x-ui.status-badge tone="{{ $user->isActive() ? 'ready' : 'neutral' }}">{{ ucfirst($user->status->value) }}</x-ui.status-badge></td>
                                <td class="px-6 py-4">{{ $user->roles->pluck('name')->join(', ') ?: 'No active role' }}</td>
                                <td class="space-y-3 px-6 py-4">
                                    <form method="POST" action="{{ route('identity.admin.users.roles', $user) }}" class="flex flex-wrap gap-2">
                                        @csrf
                                        @method('PUT')
                                        @foreach ($roles as $role)
                                            <label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" name="roles[]" value="{{ $role->code }}" @checked($user->roles->contains('code', $role->code)) />{{ $role->name }}</label>
                                        @endforeach
                                        <x-ui.button type="submit" variant="secondary">Save roles</x-ui.button>
                                    </form>
                                    @if ($user->isActive() && ($user->email || $user->mobile))
                                        <form method="POST" action="{{ route('identity.admin.users.recovery', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-sm font-bold text-brand-navy-700 underline">Issue recovery link</button>
                                        </form>
                                    @endif
                                    @if (! $user->is(auth()->user()))
                                        <form method="POST" action="{{ route('identity.admin.users.disable', $user) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-bold text-brand-red-600 underline">Disable account</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-muted">No accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-6">{{ $users->links() }}</div>
        </section>
    </div>
</x-layouts.app>
