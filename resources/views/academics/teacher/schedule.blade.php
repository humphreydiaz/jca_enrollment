<x-layouts.app title="My schedule">
    @php($weekdays = \App\Modules\Academics\Support\ClassSchedule::WEEKDAYS)
    <div class="mx-auto max-w-screen-xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <x-ui.page-header
                title="My schedule"
                eyebrow="My teaching"
                description="Your classes for the week, by day. Overlapping classes are marked so you can tell the Registrar." />
            <button type="button" class="jca-button jca-button-secondary print:hidden" onclick="window.print()">Print my schedule</button>
        </div>

        @if ($years->isNotEmpty())
            <x-academics.year-filter :action="route('academics.my-schedule')" :years="$years" :year="$year" class="print:hidden" />
        @endif

        @if (! $schedule)
            <x-ui.empty-state icon="calendar" title="No classes assigned yet" description="Your schedule appears here once the Registrar assigns you to a class." />
        @else
            <p class="text-sm font-semibold text-brand-navy-900">{{ $year->name }}</p>
            @if ($schedule['conflicts'] > 0)
                <p class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm font-semibold text-brand-red-600" role="alert">
                    <x-ui.icon name="warning" size="size-4" class="inline" /> {{ $schedule['conflicts'] }} of your classes overlap with another of your classes. Please tell the Registrar so the schedule can be corrected.
                </p>
            @endif
            @if ($schedule['advisory'] !== [])
                <p class="text-sm text-muted">Class adviser of: <span class="font-semibold text-ink">{{ implode(', ', $schedule['advisory']) }}</span></p>
            @endif

            @if ($schedule['days'] === [] && $schedule['unscheduled'] === [])
                <x-ui.empty-state icon="calendar" title="No subject classes this year" description="You have no subject classes assigned in this school year." />
            @else
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($schedule['days'] as $day => $entries)
                        <section class="jca-card overflow-hidden break-inside-avoid" aria-labelledby="day-{{ $day }}">
                            <h2 id="day-{{ $day }}" class="border-b border-border bg-brand-blue-50 px-4 py-2.5 font-black text-brand-navy-900">{{ $weekdays[$day] }}</h2>
                            <ol class="divide-y divide-border">
                                @foreach ($entries as $entry)
                                    <li class="px-4 py-3 {{ $entry['clashes_with'] !== [] ? 'bg-red-50' : '' }}">
                                        <p class="text-sm font-black tabular-nums text-brand-navy-900">{{ $entry['schedule']->startsAt }} – {{ $entry['schedule']->endsAt }}</p>
                                        <p class="text-sm font-semibold text-ink">{{ $entry['subject'] }}</p>
                                        <p class="text-xs text-muted">{{ $entry['section_label'] }}</p>
                                        @if ($entry['clashes_with'] !== [])
                                            <p class="mt-1 text-xs font-bold text-brand-red-600"><x-ui.icon name="warning" size="size-3" class="inline" /> Overlaps with {{ collect($entry['clashes_with'])->map(fn ($other) => $other['subject'].' ('.$other['section_label'].')')->implode(', ') }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endforeach
                </div>

                @if ($schedule['unscheduled'] !== [])
                    <section class="jca-card p-5" aria-labelledby="no-time">
                        <h2 id="no-time" class="font-black text-brand-navy-900">Classes without a set time</h2>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($schedule['unscheduled'] as $row)
                                <li>{{ $row['subject'] }} · {{ $row['section_label'] }} <span class="text-muted">({{ $row['schedule_label'] }})</span></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endif
        @endif
    </div>
</x-layouts.app>
