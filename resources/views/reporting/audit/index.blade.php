
<x-layouts.app title="Audit log">
    <div class="mx-auto max-w-7xl space-y-6">

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black text-brand-navy-900">
                    All Users Activity Log
                </h1>
                <p class="mt-2 text-muted">
                    Monitor important activities of administrators, registrars,
                    accounting personnel, teachers, parents, and students.
                </p>
            </div>
        </header>

        {{-- Activity Filters --}}
        <form
            class="jca-card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5"
            method="GET"
        >
            <label class="text-sm font-bold">
                Action
                <input
                    class="mt-1 w-full rounded-lg border-border"
                    name="action"
                    value="{{ request('action') }}"
                    placeholder="Search activity"
                >
            </label>

            <label class="text-sm font-bold">
                Actor Public ID
                <input
                    class="mt-1 w-full rounded-lg border-border"
                    name="actor"
                    value="{{ request('actor') }}"
                    placeholder="User ID"
                >
            </label>

            <label class="text-sm font-bold">
                Subject Reference
                <input
                    class="mt-1 w-full rounded-lg border-border"
                    name="subject_reference"
                    value="{{ request('subject_reference') }}"
                    placeholder="Record reference"
                >
            </label>

            <label class="text-sm font-bold">
                From
                <input
                    class="mt-1 w-full rounded-lg border-border"
                    type="date"
                    name="date_from"
                    value="{{ request('date_from') }}"
                >
            </label>

            <label class="text-sm font-bold">
                To
                <input
                    class="mt-1 w-full rounded-lg border-border"
                    type="date"
                    name="date_to"
                    value="{{ request('date_to') }}"
                >
            </label>

            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-5">
                <x-ui.button type="submit">
                    Search Activities
                </x-ui.button>

                <a
                    href="{{ url()->current() }}"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-bold text-brand-navy-900"
                >
                    Clear Filters
                </a>
            </div>
        </form>

        {{-- Activity Table --}}
        <section class="jca-card overflow-hidden">

            <div class="border-b border-border px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-brand-navy-900">
                        Recent User Activities
                    </h2>

                    <span class="text-sm font-semibold text-muted">
                        {{ $logs->total() }} recorded activities
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-brand-blue-100 text-brand-navy-900">
                            <th class="whitespace-nowrap p-4">
                                Date & Time
                            </th>
                            <th class="p-4">User</th>
                            <th class="p-4">Role</th>
                            <th class="p-4">Activity</th>
                            <th class="p-4">Subject Reference</th>
                            <th class="p-4">Outcome</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $actor = $log->actor;
                                $role = 'System';

                                if ($actor) {
                                    $role = 'User';

                                    if (method_exists($actor, 'getRoleNames')) {
                                        $roleNames = $actor->getRoleNames();

                                        $role = $roleNames->isNotEmpty()
                                            ? $roleNames->map(
                                                fn ($name) => ucwords(
                                                    str_replace(
                                                        ['_', '-'],
                                                        ' ',
                                                        $name
                                                    )
                                                )
                                            )->implode(', ')
                                            : 'User';
                                    }
                                }

                                $activity = ucwords(
                                    str_replace(
                                        ['.', '_', '-'],
                                        ' ',
                                        $log->action
                                    )
                                );
                            @endphp

                            <tr class="border-b border-border hover:bg-brand-blue-50">

                                <td class="whitespace-nowrap p-4">
                                    <a
                                        class="font-bold text-brand-navy-700 underline"
                                        href="{{ route('reporting.audit.show', $log) }}"
                                    >
                                        {{ $log->occurred_at->timezone(config('app.timezone'))->format('d M Y') }}
                                    </a>

                                    <div class="mt-1 text-xs text-muted">
                                        {{ $log->occurred_at->timezone(config('app.timezone'))->format('g:i:s A') }}
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-brand-navy-900">
                                        {{ $actor?->name ?? 'System' }}
                                    </div>

                                    @if ($actor?->public_id)
                                        <div class="mt-1 text-xs text-muted">
                                            {{ $actor->public_id }}
                                        </div>
                                    @endif
                                </td>

                                <td class="p-4">
                                    <span class="inline-flex rounded-lg bg-brand-blue-100 px-3 py-1 text-xs font-bold text-brand-navy-900">
                                        {{ $role }}
                                    </span>
                                </td>

                                <td class="p-4">
                                    <span class="font-semibold text-brand-navy-900">
                                        {{ $activity }}
                                    </span>
                                </td>

                                <td class="p-4 text-muted">
                                    {{ $log->subject_public_id ?? '—' }}
                                </td>

                                <td class="p-4">
                                    <x-ui.status-badge :status="$log->outcome" />
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-muted">
                                    No audit entries match the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Pagination --}}
        <div>
            {{ $logs->links() }}
        </div>
    </div>

    {{-- Automatic Refresh Every 10 Seconds --}}
    <script>
        (() => {
            const refreshInterval = 10000;

            window.setInterval(() => {
                if (document.visibilityState !== 'visible') {
                    return;
                }

                // Avoid interrupting users editing filters.
                const active = document.activeElement;

                if (
                    active &&
                    active.matches('input, select, textarea')
                ) {
                    return;
                }

                window.location.reload();
            }, refreshInterval);
        })();
    </script>
</x-layouts.app>
