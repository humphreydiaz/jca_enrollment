<x-layouts.app title="Schedule conflicts">
    <div class="mx-auto max-w-screen-xl space-y-6">
        <x-ui.page-header
            title="Schedule conflicts"
            eyebrow="Academics"
            description="Classes that meet at the same time: a teacher booked in two classes at once, or two subjects of one section at once. Fix a conflict by editing the class schedule or the teacher assignment in Academics." />

        @if ($years->isNotEmpty())
            <x-academics.year-filter :action="route('academics.schedule-conflicts')" :years="$years" :year="$year" />
        @endif

        @if (! $result)
            <x-ui.empty-state icon="calendar" title="No school year yet" description="Set up a school year and its class schedules in Academics first." />
        @else
            @php($total = count($result['teacher']) + count($result['section']))
            <section class="grid gap-4 sm:grid-cols-3" aria-label="Summary">
                <div class="jca-card p-5"><p class="text-xs font-bold uppercase tracking-wider text-muted">Classes checked</p><p class="mt-2 text-2xl font-black tabular-nums text-brand-navy-900">{{ number_format($result['offerings'] - count($result['unchecked'])) }} <span class="text-sm font-semibold text-muted">of {{ number_format($result['offerings']) }}</span></p></div>
                <div class="jca-card p-5"><p class="text-xs font-bold uppercase tracking-wider text-muted">Conflicts found</p><p class="mt-2 text-2xl font-black tabular-nums {{ $total > 0 ? 'text-brand-red-600' : 'text-success-700' }}">{{ number_format($total) }}</p></div>
                <div class="jca-card p-5"><p class="text-xs font-bold uppercase tracking-wider text-muted">Schedules that cannot be checked</p><p class="mt-2 text-2xl font-black tabular-nums {{ count($result['unchecked']) > 0 ? 'text-warning-800' : 'text-success-700' }}">{{ number_format(count($result['unchecked'])) }}</p></div>
            </section>

            @if ($total === 0)
                <p class="jca-card flex items-center gap-2 p-5 text-sm font-semibold text-success-700" role="status"><x-ui.icon name="check" size="size-5" /> No conflicts in {{ $year->name }}. Every teacher and section has at most one class at a time.</p>
            @endif

            @php($editLink = fn ($section) => route('academics.admin.index', ['year' => $year->public_id, 'section' => $section->public_id]))

            @if ($result['teacher'] !== [])
                <section class="jca-card overflow-hidden" aria-labelledby="teacher-conflicts">
                    <h2 id="teacher-conflicts" class="border-b border-border px-5 py-4 text-lg font-black text-brand-navy-900">Teacher booked in two classes at once ({{ count($result['teacher']) }})</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead class="bg-brand-blue-50 text-brand-navy-900"><tr><th scope="col" class="p-3">Teacher</th><th scope="col" class="p-3">Class</th><th scope="col" class="p-3">Clashes with</th></tr></thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($result['teacher'] as $conflict)
                                    <tr class="align-top">
                                        <td class="p-3 font-bold text-brand-navy-900"><span class="inline-flex items-center gap-1.5"><x-ui.icon name="warning" size="size-4" class="text-brand-red-600" /> {{ $conflict['teacher'] }}</span></td>
                                        @foreach (['first', 'second'] as $side)
                                            <td class="p-3"><a class="font-semibold text-brand-navy-700 underline" href="{{ $editLink($conflict[$side]['section']) }}">{{ $conflict[$side]['section_label'] }}</a><br>{{ $conflict[$side]['subject'] }} · <span class="tabular-nums">{{ $conflict[$side]['schedule_label'] }}</span></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($result['section'] !== [])
                <section class="jca-card overflow-hidden" aria-labelledby="section-conflicts">
                    <h2 id="section-conflicts" class="border-b border-border px-5 py-4 text-lg font-black text-brand-navy-900">Two subjects of one section at once ({{ count($result['section']) }})</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead class="bg-brand-blue-50 text-brand-navy-900"><tr><th scope="col" class="p-3">Section</th><th scope="col" class="p-3">Subject</th><th scope="col" class="p-3">Clashes with</th></tr></thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($result['section'] as $conflict)
                                    <tr class="align-top">
                                        <td class="p-3"><a class="inline-flex items-center gap-1.5 font-bold text-brand-navy-700 underline" href="{{ $editLink($conflict['section']) }}"><x-ui.icon name="warning" size="size-4" class="text-brand-red-600" /> {{ $conflict['first']['section_label'] }}</a></td>
                                        <td class="p-3">{{ $conflict['first']['subject'] }} · <span class="tabular-nums">{{ $conflict['first']['schedule_label'] }}</span></td>
                                        <td class="p-3">{{ $conflict['second']['subject'] }} · <span class="tabular-nums">{{ $conflict['second']['schedule_label'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($result['unchecked'] !== [])
                <section class="jca-card overflow-hidden" aria-labelledby="unchecked">
                    <h2 id="unchecked" class="border-b border-border px-5 py-4 text-lg font-black text-brand-navy-900">Schedules that cannot be checked ({{ count($result['unchecked']) }})</h2>
                    <p class="px-5 pt-3 text-sm text-muted">These classes have no schedule, or a typed note the system cannot read as days and times (for example "Mon-Fri 08:00-09:00"). Give them a day and time so conflicts can be found.</p>
                    <ul class="divide-y divide-border px-5 pb-3 text-sm">
                        @foreach ($result['unchecked'] as $row)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2.5"><span><a class="font-semibold text-brand-navy-700 underline" href="{{ $editLink($row['section']) }}">{{ $row['section_label'] }}</a> · {{ $row['subject'] }}</span><span class="text-muted">{{ $row['schedule_label'] }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif
    </div>
</x-layouts.app>
