@php
    $accountRows = $users->getCollection()->map(fn ($user) => [
        'publicId' => $user->public_id,
        'name' => $user->name,
        'contact' => $user->email ?? $user->mobile ?? 'No contact set',
        'contactType' => $user->email ? 'Email' : ($user->mobile ? 'Mobile' : 'Contact'),
        'status' => ucfirst($user->status->value),
        'roles' => $user->roles->pluck('code')->values()->all(),
        'roleNames' => $user->roles->map(fn ($role) => $role->code === 'guardian' ? 'Parent/Guardian' : $role->name)->values()->all(),
        'staffNumber' => $user->staffProfile?->employee_number,
        'parentLoginId' => $user->guardianProfile?->parent_login_id,
        'roleAction' => route('identity.admin.users.roles', $user),
        'recoveryAction' => route('identity.admin.users.recovery', $user),
        'disableAction' => route('identity.admin.users.disable', $user),
        'reactivateAction' => route('identity.admin.users.reactivate', $user),
        'canRecover' => $user->isActive() && ($user->email || $user->mobile),
        'isDisabled' => $user->disabled_at !== null,
        'isCurrent' => $user->is(auth()->user()),
    ])->values();

    $hasFilters = filled($filters['search']) || filled($filters['role']) || filled($filters['status']);
    $reopenAccount = old('account_public_id');
@endphp

<x-layouts.app title="User management">
    <div
        class="space-y-6"
        x-data="userManagement(@js($accountRows))"
        @if ($reopenAccount)
            x-init="openAccount(@js($reopenAccount), @js(old('roles')), @js(old('reason', '')))"
        @endif
    >
        <x-ui.page-header
            eyebrow="Identity & access"
            title="User management"
            description="Find accounts quickly, review access, and keep account actions in one focused workspace."
        >
            <x-slot:actions>
                <a href="#create-invitation" class="jca-button jca-button-primary">Create invitation</a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('invitation_url'))
            <section class="jca-card border-brand-gold-500 p-5 sm:p-6" aria-labelledby="invitation-link-title">
                <div class="flex gap-3">
                    <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-700">
                        <x-ui.icon name="warning" size="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 id="invitation-link-title" class="text-lg font-black text-brand-navy-900">One-time account setup fallback</h2>
                        <p class="mt-1 text-sm leading-6 text-muted">Use this only if the SMS does not arrive. Deliver it through the verified contact channel; it will not be shown again.</p>
                        @if (session('invitation_code'))
                            <p class="mt-3 text-sm text-muted">Account code</p>
                            <input readonly value="{{ session('invitation_code') }}" class="jca-input mt-1 font-mono tracking-wider" aria-label="One-time account code" />
                        @endif
                        <p class="mt-3 text-sm text-muted">Direct setup link</p>
                        <input readonly value="{{ session('invitation_url') }}" class="jca-input mt-3 font-mono text-xs" aria-label="One-time invitation link" />
                    </div>
                </div>
            </section>
        @endif

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Account summary">
            <div class="jca-card p-4 sm:p-5">
                <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-muted">All accounts</p>
                <p class="mt-2 text-2xl font-black text-brand-navy-900">{{ number_format($summary['total']) }}</p>
            </div>
            <div class="jca-card p-4 sm:p-5">
                <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-muted">Active</p>
                <p class="mt-2 text-2xl font-black text-emerald-700">{{ number_format($summary['active']) }}</p>
            </div>
            <a
                href="{{ route('identity.admin.users.index', ['status' => 'invited']) }}"
                class="jca-card block p-4 sm:p-5"
                title="View active and pending staff accounts"
            >
                <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-muted">Invited</p>
                <p class="mt-2 text-2xl font-black text-brand-navy-700">{{ number_format($summary['invited']) }}</p>
            </a>
            <div class="jca-card p-4 sm:p-5">
                <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-muted">Needs attention</p>
                <p class="mt-2 text-2xl font-black text-brand-red-600">{{ number_format($summary['attention']) }}</p>
            </div>
        </section>

        <details id="create-invitation" class="jca-card overflow-hidden" @if ($errors->any() && ! $reopenAccount) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 marker:hidden sm:p-6 [&::-webkit-details-marker]:hidden">
                <span>
                    <span class="block text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Admin only</span>
                    <span class="mt-1 block text-lg font-black text-brand-navy-900">Create an invited account</span>
                </span>
                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-blue-100 text-xl font-bold text-brand-navy-700" aria-hidden="true">+</span>
            </summary>

            <div class="border-t border-border p-5 sm:p-6">
                <p class="max-w-3xl text-sm leading-6 text-muted">Public registration is disabled. Create a staff account, select one staff role, and issue a single-use setup link.</p>

                <form method="POST" action="{{ route('identity.admin.users.store') }}" class="mt-6 grid gap-5 md:grid-cols-2" x-data="{ staffRole: @js(old('roles.0', '')), employmentDate: @js(old('employed_on', '')) }">
                    @csrf
                    <fieldset class="md:col-span-2">
                        <legend class="text-sm font-bold text-ink">Staff role <span class="text-brand-red-600">*</span></legend>
                        <p class="mt-1 text-sm text-muted">Select one role for this staff invitation.</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($roles->whereIn('code', ['admin', 'registrar', 'accounting', 'teacher']) as $role)
                                <label class="jca-check rounded-lg border border-border p-3">
                                    <input type="radio" name="roles[]" value="{{ $role->code }}" required @checked(in_array($role->code, old('roles', []), true)) x-model="staffRole" />
                                    {{ $role->name }}
                                </label>
                            @endforeach
                        </div>
                        @if (! $reopenAccount)
                            @error('roles')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                            @error('roles.0')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                            @error('employed_on')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                        @endif
                    </fieldset>

                    <div x-show="staffRole !== ''" x-cloak class="md:col-span-2 rounded-xl border border-brand-blue-100 bg-brand-blue-100/30 p-4 sm:p-5" aria-live="polite">
                        <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-brand-navy-700">Automatic Staff ID preview</p>
                        <p class="mt-2 font-mono text-xl font-black text-brand-navy-900"
                           x-text="staffRole ? (/^\d{4}-\d{2}-\d{2}$/.test(employmentDate) ? employmentDate.slice(2, 4) : @js(today()->format('y'))) + '-JCA-###' : 'Select a staff role first'">
                            Select a staff role first
                        </p>
                        <p class="mt-2 text-sm text-muted">Preview only. The final unique three digits are generated when you create the invitation. The year updates when you set the employment start date.</p>
                    </div>

                    <div class="md:col-span-2 mt-1">
                        <h3 class="text-sm font-black text-brand-navy-900">Staff information</h3>
                        <p class="mt-1 text-sm text-muted">Enter the staff details after selecting the role.</p>
                    </div>
                    <x-ui.field for="name" label="Full name">
                        <input id="name" name="name" value="{{ old('name') }}" required class="jca-input mt-0" />
                    </x-ui.field>
                    <x-ui.field for="channel" label="Invitation channel">
                        <input type="hidden" name="channel" value="mobile">
                        <div id="channel" class="jca-input mt-0 flex items-center bg-slate-50 font-semibold text-ink" aria-label="Invitation channel: Mobile">
                            Mobile
                        </div>
                    </x-ui.field>
                    <x-ui.field for="email" label="Email address (optional)">
                        <input id="email" name="email" value="{{ old('email') }}" type="email" autocomplete="email" class="jca-input mt-0" />
                    </x-ui.field>
                    <x-ui.field for="mobile" label="Mobile number">
                        <input id="mobile" name="mobile" value="{{ old('mobile') }}" inputmode="tel" autocomplete="tel" required class="jca-input mt-0" />
                    </x-ui.field>
                    <x-ui.field for="employed_on" label="Employment start date" hint="Required for staff invitations; this date supplies the Staff ID year.">
                        <input id="employed_on" name="employed_on" value="{{ old('employed_on') }}" type="date" max="{{ today()->toDateString() }}" required class="jca-input mt-0" x-model="employmentDate" />
                    </x-ui.field>


                    <div class="md:col-span-2">
                        <x-ui.button type="submit">Create invitation</x-ui.button>
                    </div>
                </form>
            </div>
        </details>

        <details class="jca-card overflow-hidden" id="reissue-password">
    <summary class="cursor-pointer p-5 font-black text-brand-navy-900 sm:p-6">
        Reissue Temporary Password
    </summary>
    <div class="border-t border-border p-5 sm:p-6">
        <p class="mb-4 text-sm text-muted">
            Find an existing account using a Parent ID, Student ID, or Staff ID.
        </p>
        <a href="{{ route('identity.users.reissue.index') }}" class="jca-button jca-button-primary">
            Open Reissue Temporary Password
        </a>
    </div>
</details>
        <section class="jca-card overflow-hidden" aria-labelledby="accounts-title">
            <div class="border-b border-border p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 id="accounts-title" class="text-xl font-black text-brand-navy-900">Accounts</h2>
                        <p class="mt-1 text-sm text-muted">Search by name, email, or mobile number, then narrow the list by role or status.</p>
                    </div>
                    <p class="text-sm font-semibold text-muted" aria-live="polite">{{ number_format($users->total()) }} {{ Str::plural('result', $users->total()) }}</p>
                </div>

                <form method="GET" action="{{ route('identity.admin.users.index') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(16rem,1.5fr)_minmax(9rem,0.75fr)_minmax(9rem,0.75fr)_minmax(11rem,0.85fr)_7rem_auto]" aria-label="Filter accounts">
                    <div>
                        <label for="account-search" class="sr-only">Search accounts</label>
                        <input id="account-search" name="search" value="{{ $filters['search'] }}" type="search" class="jca-input mt-0" placeholder="Search name, email, or mobile" />
                    </div>
                    <div>
                        <label for="account-role" class="sr-only">Filter by role</label>
                        <select id="account-role" name="role" class="jca-input mt-0">
                            <option value="">All roles</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->code }}" @selected($filters['role'] === $role->code)>{{ $role->code === 'guardian' ? 'Parent/Guardian' : $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="account-status" class="sr-only">Filter by status</label>
                        <select id="account-status" name="status" class="jca-input mt-0">
                            <option value="">All statuses</option>
                            @foreach (\App\Modules\Identity\Enums\AccountStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ ucfirst($status->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="account-sort" class="sr-only">Sort accounts</label>
                        <select id="account-sort" name="sort" class="jca-input mt-0">
                            <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Name A-Z</option>
                            <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Name Z-A</option>
                            <option value="newest" @selected($filters['sort'] === 'newest')>Newest accounts</option>
                            <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest accounts</option>
                            <option value="recent_login" @selected($filters['sort'] === 'recent_login')>Recent sign-in</option>
                        </select>
                    </div>
                    <div>
                        <label for="account-per-page" class="sr-only">Results per page</label>
                        <select id="account-per-page" name="per_page" class="jca-input mt-0">
                            @foreach ([15, 30, 50] as $size)
                                <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }} / page</option>
                            @endforeach
                        </select>
                    </div>
                    <x-ui.button type="submit">Apply</x-ui.button>
                </form>

                @if ($hasFilters)
                    <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Active filters">
                        <span class="text-xs font-extrabold uppercase tracking-[0.12em] text-muted">Active filters</span>
                        @if (filled($filters['search']))
                            <a href="{{ route('identity.admin.users.index', request()->except(['search', 'page'])) }}" class="inline-flex min-h-9 items-center gap-2 rounded-full bg-brand-blue-100 px-3 text-xs font-bold text-brand-navy-700">
                                Search: {{ Str::limit($filters['search'], 28) }} <span aria-hidden="true">x</span>
                            </a>
                        @endif
                        @if (filled($filters['role']))
                            <a href="{{ route('identity.admin.users.index', request()->except(['role', 'page'])) }}" class="inline-flex min-h-9 items-center gap-2 rounded-full bg-brand-blue-100 px-3 text-xs font-bold text-brand-navy-700">
                                Role: {{ $filters['role'] === 'guardian' ? 'Parent/Guardian' : $roles->firstWhere('code', $filters['role'])?->name }} <span aria-hidden="true">x</span>
                            </a>
                        @endif
                        @if (filled($filters['status']))
                            <a href="{{ route('identity.admin.users.index', request()->except(['status', 'page'])) }}" class="inline-flex min-h-9 items-center gap-2 rounded-full bg-brand-blue-100 px-3 text-xs font-bold text-brand-navy-700">
                                Status: {{ ucfirst($filters['status']) }} <span aria-hidden="true">x</span>
                            </a>
                        @endif
                        <a href="{{ route('identity.admin.users.index') }}" class="min-h-9 px-2 py-2 text-xs font-bold text-brand-navy-700 underline">Clear all</a>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-left text-sm">
                    <thead class="bg-canvas text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-bold sm:px-6">User</th>
                            <th scope="col" class="hidden px-5 py-3 font-bold md:table-cell sm:px-6">Contact</th>
                            <th scope="col" class="px-5 py-3 font-bold sm:px-6">Roles</th>
                            <th scope="col" class="px-5 py-3 font-bold sm:px-6">Status</th>
                            <th scope="col" class="hidden px-5 py-3 font-bold xl:table-cell sm:px-6">Last sign-in</th>
                            <th scope="col" class="px-5 py-3 text-right font-bold sm:px-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-white">
                        @forelse ($users as $user)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex min-w-48 items-center gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-blue-100 text-sm font-black text-brand-navy-700" aria-hidden="true">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                                        <div class="min-w-0">
                                            <p class="font-bold text-ink">{{ $user->name }}</p>
                                            @if ($user->staffProfile?->employee_number)
                                                <p class="mt-0.5 font-mono text-xs font-semibold text-brand-navy-700">{{ $user->staffProfile->employee_number }}</p>
                                            @endif
                                            @if ($user->is(auth()->user()))
                                                <p class="mt-0.5 text-xs font-semibold text-brand-navy-700">Your account</p>
                                            @endif
                                            <p class="mt-1 text-xs text-muted md:hidden">{{ $user->email ?? $user->mobile ?? 'No contact set' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden max-w-64 px-5 py-4 text-muted md:table-cell sm:px-6">
                                    <span class="break-all">{{ $user->email ?? $user->mobile ?? 'No contact set' }}</span>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex min-w-36 flex-wrap gap-1.5">
                                        @forelse ($user->roles as $role)
                                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ $role->code === 'guardian' ? 'Parent/Guardian' : $role->name }}</span>
                                        @empty
                                            <span class="text-xs font-semibold text-muted">No active role</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
    @if ($user->status === \App\Modules\Identity\Enums\AccountStatus::Invited && $user->hasRole('guardian') && ! $user->roles->contains('is_staff', true))
        <span class="font-semibold text-muted">Pending Setup</span>
    @else
<x-ui.status-badge :status="$user->status" />
    @endif
</td>
                                <td class="hidden whitespace-nowrap px-5 py-4 text-muted xl:table-cell sm:px-6">
                                    {{ $user->last_login_at?->timezone(config('app.timezone'))->format('d M Y, g:i A') ?? 'Never' }}
                                </td>
                                <td class="px-5 py-4 text-right sm:px-6">
                                    <button
                                        type="button"
                                        class="jca-button jca-button-secondary whitespace-nowrap"
                                        data-testid="manage-account-{{ $user->public_id }}"
                                        @click="openAccount('{{ $user->public_id }}')"
                                        aria-haspopup="dialog"
                                    >
                                        Manage
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 sm:px-6">
                                    <x-ui.empty-state
                                        icon="users"
                                        :title="$hasFilters ? 'No accounts match these filters' : 'No accounts yet'"
                                        :description="$hasFilters ? 'Remove a filter or try a broader search.' : 'Create an invitation when the first account is ready.'"
                                    >
                                        @if ($hasFilters)
                                            <x-slot:action><a href="{{ route('identity.admin.users.index') }}" class="jca-button jca-button-secondary">Clear filters</a></x-slot:action>
                                        @endif
                                    </x-ui.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages() || $users->total() > 0)
                <div class="flex flex-col gap-4 border-t border-border p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-sm text-muted">
                        Showing <span class="font-bold text-ink">{{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }}</span>
                        of <span class="font-bold text-ink">{{ number_format($users->total()) }}</span>
                    </p>
                    <div>{{ $users->onEachSide(1)->links() }}</div>
                </div>
            @endif
        </section>

        <dialog
            x-ref="accountDialog"
            @close="selectedUser = null"
            @cancel="selectedUser = null"
            @keydown.escape.window="closeAccount()"
            @click.self="closeAccount()"
            class="m-auto w-[min(44rem,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55"
            aria-labelledby="account-dialog-title"
        >
            <div x-show="selectedUser" x-cloak>
                <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-white px-5 py-5 sm:px-6">
                    <div class="min-w-0">
                        <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Account actions</p>
                        <h2 id="account-dialog-title" class="mt-1 truncate text-xl font-black text-brand-navy-900" x-text="selectedUser?.name"></h2>
                        <p class="mt-1 break-all text-sm text-muted"><span x-text="selectedUser?.contactType"></span>: <span x-text="selectedUser?.contact"></span></p>
                        <p x-show="selectedUser?.staffNumber" class="mt-1 font-mono text-sm font-bold text-brand-navy-700" x-text="selectedUser?.staffNumber"></p>
                    </div>
                    <button type="button" class="grid size-10 shrink-0 place-items-center rounded-lg text-muted hover:bg-slate-100 hover:text-ink" @click="closeAccount()" aria-label="Close account actions">
                        <x-ui.icon name="close" />
                    </button>
                </header>

                <div class="space-y-5 p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700" x-text="selectedUser?.status"></span>
                        <template x-for="roleName in selectedUser?.roleNames ?? []" :key="roleName">
                            <span class="rounded-md bg-brand-blue-100 px-2 py-1 text-xs font-bold text-brand-navy-700" x-text="roleName"></span>
                        </template>
                    </div>


                    <template x-if="(selectedUser?.roles ?? []).includes('guardian') && selectedUser?.parentLoginId">
                        <section class="rounded-xl border border-border p-4 sm:p-5">
                            <h3 class="font-black text-brand-navy-900">Parent ID</h3>
                            <p
                                class="mt-2 font-mono text-xl font-black text-brand-navy-900"
                                x-text="selectedUser?.parentLoginId"
                            ></p>
                            <p class="mt-1 text-xs text-muted">
                                Grade 7–10 Parent/Guardian login identifier.
                            </p>
                        </section>
                    </template>
                    <template x-if="selectedUser?.canRecover">
                        <section class="rounded-xl border border-border p-4 sm:p-5" aria-labelledby="account-recovery-title">
                            <h3 id="account-recovery-title" class="font-black text-brand-navy-900">Account recovery</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Create a single-use recovery link after the school has verified this person's identity.</p>
                            <form method="POST" :action="selectedUser?.recoveryAction" class="mt-4">
                                @csrf
                                <input type="hidden" name="account_public_id" :value="selectedUser?.publicId" />
                                <x-ui.button type="submit" variant="secondary">Issue recovery link</x-ui.button>
                            </form>
                        </section>
                    </template>

                    <template x-if="selectedUser?.isDisabled">
                        <section class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 sm:p-5" aria-labelledby="reactivate-account-title">
                            <h3 id="reactivate-account-title" class="font-black text-brand-navy-900">Reactivate account</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Staff reactivation issues a setup link. Parent/Student reactivation issues a temporary password.</p>
                            <form method="POST" :action="selectedUser?.reactivateAction" class="mt-4">
                                @csrf
                                <input type="hidden" name="account_public_id" :value="selectedUser?.publicId" />
                                <label for="reactivate-reason" class="jca-label">Reason for reactivation</label>
                                <textarea id="reactivate-reason" name="reason" required maxlength="2000" class="jca-input" x-model="reason" placeholder="Identity verified and account approved for reactivation"></textarea>
                                @if ($reopenAccount)
                                    @error('reason')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                                @endif
                                <div class="mt-4">
                                    <x-ui.button type="submit">Reactivate and issue setup link</x-ui.button>
                                </div>
                            </form>
                        </section>
                    </template>

                    <template x-if="! selectedUser?.isDisabled && ! selectedUser?.isCurrent">
                        <section class="rounded-xl border border-red-200 bg-red-50/60 p-4 sm:p-5" aria-labelledby="disable-account-title">
                            <h3 id="disable-account-title" class="font-black text-brand-red-600">Disable account</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">This revokes active sessions and outstanding invitations while preserving the account history.</p>
                            <form method="POST" :action="selectedUser?.disableAction" class="mt-4">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="account_public_id" :value="selectedUser?.publicId" />
                                <label for="disable-reason" class="jca-label">Reason for disabling</label>
                                <textarea id="disable-reason" name="reason" required maxlength="2000" class="jca-input" x-model="reason" placeholder="Explain why this account must be disabled"></textarea>
                                @if ($reopenAccount)
                                    @error('reason')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                                @endif
                                <div class="mt-4">
                                    <x-ui.button type="submit" variant="danger">Disable account</x-ui.button>
                                </div>
                            </form>
                        </section>
                    </template>

                    <template x-if="selectedUser?.isCurrent">
                        <p class="rounded-lg bg-slate-100 p-3 text-sm font-semibold text-slate-700">You cannot disable the account you are currently using.</p>
                    </template>
                </div>

                <footer class="sticky bottom-0 flex justify-end border-t border-border bg-white px-5 py-4 sm:px-6">
                    <x-ui.button type="button" variant="secondary" @click="closeAccount()">Close</x-ui.button>
                </footer>
            </div>
        </dialog>
    </div>
</x-layouts.app>
