<x-layouts.app title="Information updates">
    <div class="mx-auto max-w-7xl space-y-6">
        <x-ui.page-header
            title="Information updates"
            description="Changes that students and parents/guardians made to their own My Information page. Contact numbers and emails are shown masked."
            eyebrow="School records"
        />

        <form class="jca-card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4" method="GET">
            <label class="text-sm font-bold">
                Updated by (name)
                <input class="mt-1 w-full rounded-lg border-border" name="name" value="{{ request('name') }}" maxlength="100" placeholder="Student or parent name">
            </label>
            <label class="text-sm font-bold">
                Account
                <select class="mt-1 w-full rounded-lg border-border" name="account">
                    <option value="">Students and parents</option>
                    <option value="student" @selected(request('account') === 'student')>Students only</option>
                    <option value="guardian" @selected(request('account') === 'guardian')>Parents/guardians only</option>
                </select>
            </label>
            <label class="text-sm font-bold">
                From
                <input class="mt-1 w-full rounded-lg border-border" type="date" name="date_from" value="{{ request('date_from') }}">
            </label>
            <label class="text-sm font-bold">
                To
                <input class="mt-1 w-full rounded-lg border-border" type="date" name="date_to" value="{{ request('date_to') }}">
            </label>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-4">
                <x-ui.button type="submit">Search updates</x-ui.button>
                <a href="{{ url()->current() }}" class="rounded-lg border border-border px-4 py-2 text-sm font-bold text-brand-navy-900">Clear filters</a>
            </div>
        </form>

        <section class="jca-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                <h2 class="text-lg font-black text-brand-navy-900">Recent information updates</h2>
                <span class="text-sm font-semibold text-muted">{{ $logs->total() }} {{ \Illuminate\Support\Str::plural('update', $logs->total()) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-brand-blue-100 text-brand-navy-900">
                            <th class="whitespace-nowrap p-4">Date &amp; time</th>
                            <th class="p-4">Updated by</th>
                            <th class="p-4">Account</th>
                            <th class="p-4">What changed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php($lines = \App\Modules\Shared\Audit\AuditChangeSummary::lines($log))
                            <tr class="border-b border-border align-top hover:bg-brand-blue-50">
                                <td class="whitespace-nowrap p-4">
                                    <a class="font-bold text-brand-navy-700 underline" href="{{ route('reporting.audit.show', $log) }}">
                                        {{ $log->occurred_at->timezone(config('app.timezone'))->format('d M Y') }}
                                    </a>
                                    <div class="mt-1 text-xs text-muted">{{ $log->occurred_at->timezone(config('app.timezone'))->format('g:i A') }}</div>
                                </td>
                                <td class="p-4 font-bold text-brand-navy-900">{{ $log->actor?->name ?? 'Unknown account' }}</td>
                                <td class="p-4">
                                    <span class="inline-flex rounded-lg bg-brand-blue-100 px-3 py-1 text-xs font-bold text-brand-navy-900">
                                        {{ $log->action === 'people.student.self_profile_updated' ? 'Student' : 'Parent/Guardian' }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    @if ($lines === [])
                                        <span class="text-muted">Details saved (no field list recorded)</span>
                                    @else
                                        <x-audit.change-lines :lines="$lines" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-8 text-center text-muted">No information updates match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div>{{ $logs->links() }}</div>
    </div>
</x-layouts.app>
