<x-layouts.app title="Top students">
    <div class="mx-auto max-w-screen-xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <x-ui.page-header
                title="Top students"
                eyebrow="Insights"
                description="The ten learners with the highest average per grade level or section. Ties share a place." />
            <button type="button" class="jca-button jca-button-secondary print:hidden" onclick="window.print()">Print</button>
        </div>

        <form method="GET" action="{{ route('reporting.top-students') }}" class="jca-card flex flex-wrap items-end gap-3 p-4 print:hidden">
            <label class="text-sm font-bold">School year
                <select name="year" class="mt-1 block rounded-lg border-border">
                    @foreach ($years as $option)
                        <option value="{{ $option->public_id }}" @selected($year?->public_id === $option->public_id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-bold">Rank within
                <select name="group" class="mt-1 block rounded-lg border-border">
                    <option value="grade" @selected($group === 'grade')>Each grade level</option>
                    <option value="section" @selected($group === 'section')>Each section</option>
                </select>
            </label>
            <label class="text-sm font-bold">Based on
                <select name="basis" class="mt-1 block rounded-lg border-border">
                    <option value="final" @selected($basis === 'final')>Approved final grades</option>
                    <option value="projected" @selected($basis === 'projected')>Projected (includes submitted, not yet approved)</option>
                </select>
            </label>
            <x-ui.button type="submit">Show</x-ui.button>
        </form>

        @if ($year)
            <p class="text-sm font-semibold text-brand-navy-900">{{ $year->name }} · {{ $group === 'section' ? 'per section' : 'per grade level' }} · {{ $basis === 'final' ? 'approved final grades' : 'projected' }}</p>
        @endif
        @if ($basis === 'projected')
            <p class="rounded-lg border border-amber-200 bg-warning-100 p-3 text-sm font-semibold text-warning-800" role="note">
                Projected ranking: subjects without an approved final grade use the average of the quarter grades their teacher has submitted. These grades are not yet approved by the Registrar and may change. Teachers' drafts are never used. Do not announce honors from this view.
            </p>
        @else
            <p class="text-sm text-muted">Only learners whose subjects all have an approved final grade are ranked. The average is a plain mean of approved final grades; honors and the official general average remain the school's decision.</p>
        @endif

        @if (! $result || $result['groups'] === [])
            <x-ui.empty-state icon="reports" title="No grades to rank yet" description="Rankings appear once {{ $basis === 'final' ? 'final grades are approved by the Registrar' : 'teachers submit quarter grades' }}." />
        @else
            <div class="grid gap-5 lg:grid-cols-2">
                @foreach ($result['groups'] as $entry)
                    <section class="jca-card overflow-hidden" aria-labelledby="top-{{ $loop->index }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border px-5 py-3">
                            <h2 id="top-{{ $loop->index }}" class="text-lg font-black text-brand-navy-900">{{ $entry['label'] }}</h2>
                            <span class="text-xs text-muted">{{ $entry['ranked'] }} ranked{{ $entry['incomplete'] > 0 ? ' · '.$entry['incomplete'].' not yet complete' : '' }}</span>
                        </div>
                        @if ($entry['rows'] === [])
                            <p class="px-5 py-4 text-sm text-muted">No learner in this group has a complete set of approved final grades yet.</p>
                        @else
                            <table class="w-full text-left text-sm">
                                <thead class="bg-brand-blue-50 text-brand-navy-900"><tr><th scope="col" class="w-14 p-2.5 text-center">Place</th><th scope="col" class="p-2.5">Learner</th>@if ($group === 'grade')<th scope="col" class="p-2.5">Section</th>@endif<th scope="col" class="p-2.5 text-right">Average</th><th scope="col" class="p-2.5 text-right">Subjects</th></tr></thead>
                                <tbody class="divide-y divide-border">
                                    @foreach ($entry['rows'] as $row)
                                        <tr>
                                            <td class="p-2.5 text-center font-black tabular-nums text-brand-navy-900">{{ $row['rank'] }}</td>
                                            <td class="p-2.5"><span class="font-semibold text-ink">{{ $row['name'] }}</span> <span class="block font-mono text-xs text-muted">{{ $row['student_number'] }}</span></td>
                                            @if ($group === 'grade')<td class="p-2.5">{{ $row['section'] }}</td>@endif
                                            <td class="p-2.5 text-right font-black tabular-nums text-brand-navy-900">{{ number_format($row['average'], 2) }}</td>
                                            <td class="p-2.5 text-right tabular-nums text-muted">{{ $row['subjects'] }}{{ $row['required'] > 0 ? ' / '.$row['required'] : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
