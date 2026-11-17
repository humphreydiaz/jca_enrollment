{{-- One class for review: used in the Grade approval dialog and on the full-page fallback. --}}
@php
    $waitingCount = $class['approvable']->count();
    $flaggedCount = collect($class['rows'])->filter(fn ($row) => $row['flags'] !== [])->count();
    $done = $class['counts']['approved'] + $class['counts']['submitted'];
@endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="text-sm">
            <p class="font-semibold text-brand-navy-900">{{ $class['teacherNames'] }}</p>
            <p class="text-muted">{{ $done }} of {{ $class['total'] }} submitted · {{ $class['counts']['approved'] }} approved</p>
        </div>
        @if ($flaggedCount)
            <p class="rounded-full bg-warning-100 px-3 py-1 text-xs font-extrabold text-warning-800">{{ $flaggedCount }} to check</p>
        @endif
    </div>
    <x-grading.stages :counts="$class['counts']" :total="$class['total']" />

    @if ($class['remaining'] > 0 && $waitingCount > 0)
        <p class="rounded-xl bg-canvas p-3 text-sm text-muted">{{ $class['remaining'] }} {{ Str::plural('student', $class['remaining']) }} still {{ $class['remaining'] === 1 ? 'has' : 'have' }} no submitted grade. You can approve the submitted ones now; the rest will appear when the teacher submits them.</p>
    @endif

    <div class="relative overflow-x-auto rounded-xl border border-border">
        <table class="w-full min-w-[38rem] text-left text-sm">
            <thead class="bg-canvas text-xs font-extrabold uppercase tracking-wider text-muted">
                <tr>
                    <th scope="col" class="px-3 py-2.5">Student</th>
                    <th scope="col" class="px-2 py-2.5 text-center">Q1</th>
                    <th scope="col" class="px-2 py-2.5 text-center">Q2</th>
                    <th scope="col" class="px-2 py-2.5 text-center">Q3</th>
                    <th scope="col" class="px-2 py-2.5 text-center">Q4</th>
                    <th scope="col" class="px-2 py-2.5 text-center">Final</th>
                    <th scope="col" class="px-3 py-2.5">Status</th>
                    <th scope="col" class="px-3 py-2.5"><span class="sr-only">Action</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach ($class['rows'] as $row)
                    @php($grade = $row['grade'])
                    <tr class="{{ $row['flags'] ? 'bg-warning-50' : '' }}">
                        <th scope="row" class="px-3 py-2 font-bold text-brand-navy-900">
                            {{ $row['student']->last_name }}, {{ $row['student']->first_name }}
                            <span class="block text-xs font-normal tabular-nums text-muted">{{ $row['student']->student_number }}</span>
                            @foreach ($row['flags'] as $flag)
                                <span class="mt-0.5 block text-xs font-bold text-warning-800">⚑ {{ $flag }}</span>
                            @endforeach
                        </th>
                        @foreach (['quarter_1', 'quarter_2', 'quarter_3', 'quarter_4'] as $quarter)
                            @php($value = $grade?->{$quarter})
                            <td class="px-2 py-2 text-center tabular-nums {{ $value !== null && $value < \App\Modules\Grading\Support\GradeReviewFlags::LOW_QUARTER ? 'font-black text-warning-800' : '' }}">{{ $value ?? '—' }}</td>
                        @endforeach
                        <td class="px-2 py-2 text-center text-base font-black tabular-nums text-brand-navy-900">{{ $grade?->final_grade ?? '—' }}</td>
                        <td class="px-3 py-2"><x-grading.stage-badge :stage="$row['stage']" /></td>
                        <td class="px-3 py-2 text-right">
                            @if ($row['stage'] === 'submitted')
                                <form method="POST" action="{{ route('grading.admin.finalize', $grade) }}">
                                    @csrf
                                    <button class="whitespace-nowrap rounded-lg border border-brand-navy-700/30 px-3 py-1.5 text-xs font-bold text-brand-navy-700 hover:bg-brand-blue-100 focus-visible:outline-2 focus-visible:outline-brand-navy-700" type="submit">Approve<span class="sr-only"> {{ $row['student']->full_name }}</span></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($waitingCount > 0)
        <form method="POST" action="{{ route('grading.admin.approve-classes') }}" class="sticky bottom-0 z-10 -mx-1 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-blue-100 bg-white/95 p-3 shadow-[0_-8px_24px_-12px_rgba(23,22,79,0.35)] backdrop-blur">
            @csrf
            <input type="hidden" name="classes[]" value="{{ $class['key'] }}">
            <p class="text-xs text-muted">Approves the {{ $waitingCount }} waiting {{ Str::plural('grade', $waitingCount) }} shown here. A grade changed after you opened this class is skipped.</p>
            <x-ui.button type="submit">Approve all {{ $waitingCount }}</x-ui.button>
        </form>
    @else
        <p class="rounded-xl bg-canvas p-3 text-sm text-muted">Nothing in this class is waiting for approval.</p>
    @endif
</div>
