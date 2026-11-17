@php
    // D-051: one tab per school year (newest first) so past years never pile up on screen.
    $today = today();
    $periodsByYear = $periods->groupBy('academic_year_id');
    $currentYear = $academicYears->first(fn ($year) => $year->starts_on?->lte($today) && $year->ends_on?->gte($today));
    $tabYears = $academicYears->filter(fn ($year) => $periodsByYear->has($year->id) || $currentYear?->is($year))->values();
    // Stay on the year just added to (the section is re-rendered after saving), otherwise open the current year.
    $requestedYearId = request()->routeIs('billing.configuration.exam-periods') ? request()->integer('academic_year_id') : (int) old('academic_year_id');
    $selectedYear = ($requestedYearId ? $academicYears->firstWhere('id', $requestedYearId) : null)
        ?? $currentYear ?? $tabYears->first() ?? $academicYears->first();
    $tabYears = $tabYears->when($selectedYear && ! $tabYears->contains(fn ($year) => $year->is($selectedYear)), fn ($years) => $years->push($selectedYear)->sortByDesc('starts_on')->values());
@endphp

<section id="exam-periods" data-section="exam-periods" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="exam-periods-title"
    x-data="{ year: @js((string) $selectedYear?->id), adding: @js($errors->hasAny(['code', 'name', 'starts_on', 'ends_on', 'eligibility_cutoff_on', 'issue_opens_on', 'issue_closes_on'])) }">
    <div class="border-b border-border px-5 py-4 sm:px-6">
        <h2 id="exam-periods-title" class="text-xl font-black text-brand-navy-900">Examination periods</h2>
        <p class="mt-1 text-sm text-muted">The exams in each school year, with the payment cutoff for permits and when permits can be issued. Quarterly payment plans need four exams in the learner's school year; Semiannual plans need three.</p>
    </div>

    <div class="space-y-5 p-5 sm:p-6">
        @if ($tabYears->isEmpty())
            <x-ui.empty-state icon="academics" title="No examination periods" description="Add the first approved period below." />
        @else
            <div class="flex gap-1 overflow-x-auto rounded-xl border border-border bg-white p-1.5 shadow-sm" role="tablist" aria-label="School years">
                @foreach ($tabYears as $tabYear)
                    @php
                        $count = $periodsByYear->get($tabYear->id, collect())->count();
                        $isCurrent = $currentYear?->is($tabYear);
                    @endphp
                    <button type="button" role="tab" id="exam-tab-{{ $tabYear->id }}" aria-controls="exam-panel-{{ $tabYear->id }}"
                        @click="year = @js((string) $tabYear->id)" :aria-selected="(year === @js((string) $tabYear->id)).toString()"
                        :class="year === @js((string) $tabYear->id) ? 'bg-brand-navy-700 text-white' : 'text-brand-navy-700 hover:bg-brand-blue-100'"
                        class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-bold transition-colors">
                        <span><span class="hidden sm:inline">School Year </span>{{ $tabYear->code }}</span>
                        <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs font-extrabold tabular-nums" aria-label="{{ $count }} {{ \Illuminate\Support\Str::plural('exam', $count) }}">{{ $count }}</span>
                        @if ($isCurrent)
                            <span class="rounded-full bg-brand-gold-500 px-2 py-0.5 text-xs font-extrabold uppercase tracking-wide text-brand-navy-900">Current</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @foreach ($tabYears as $tabYear)
                @php
                    $yearPeriods = $periodsByYear->get($tabYear->id, collect())->sortBy('starts_on')->values();
                    $count = $yearPeriods->count();
                    $next = $yearPeriods->first(fn ($period) => $period->ends_on->gte($today));
                @endphp
                <div id="exam-panel-{{ $tabYear->id }}" role="tabpanel" aria-labelledby="exam-tab-{{ $tabYear->id }}"
                    x-show="year === @js((string) $tabYear->id)" @if (! $selectedYear?->is($tabYear)) x-cloak @endif class="space-y-4">
                    <div @class([
                        'flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm',
                        'bg-emerald-50 text-emerald-900' => $count >= 4,
                        'bg-warning-100 text-warning-800' => $count < 4,
                    ])>
                        <p>
                            <strong>{{ $tabYear->name }}</strong> ·
                            {{ $tabYear->starts_on?->format('M j, Y') }} – {{ $tabYear->ends_on?->format('M j, Y') }} ·
                            {{ $count }} {{ \Illuminate\Support\Str::plural('exam', $count) }}
                        </p>
                        <p class="font-bold">
                            @if ($count >= 4)
                                Quarterly and Semiannual plans available
                            @elseif ($count === 3)
                                Semiannual available · Quarterly needs {{ 4 - $count }} more
                            @else
                                Quarterly needs {{ 4 - $count }} more · Semiannual needs {{ 3 - $count }} more
                            @endif
                        </p>
                    </div>

                    @if ($yearPeriods->isEmpty())
                        <x-ui.empty-state icon="academics" title="No examination periods for this school year" description="Add the school calendar's exams with “Add an examination period” below." />
                    @else
                        <ol class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-white">
                            @foreach ($yearPeriods as $period)
                                @php
                                    $isNext = $next?->is($period);
                                    $isPast = $period->ends_on->lt($today);
                                    $sameDay = $period->ends_on->isSameDay($period->starts_on);
                                @endphp
                                <li @class(['px-4 py-3', 'bg-brand-blue-100/50' => $isNext])>
                                    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                        <p class="min-w-0">
                                            <span class="font-bold {{ $isPast ? 'text-slate-600' : 'text-brand-navy-900' }}">{{ $period->name }}</span>
                                            <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-bold text-slate-600">{{ $period->code }}</span>
                                            @if ($isNext)<span class="ml-1 rounded bg-brand-navy-700 px-1.5 py-0.5 text-xs font-bold text-white">Next exam</span>@endif
                                        </p>
                                        <div class="flex flex-wrap items-center gap-2">
    <x-ui.status-badge :status="$period->effective_status" />

    @if ($period->payment_cutoff_status === 'closed')
        <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
            Payment Cutoff Closed
        </span>
    @else
        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">
            Payment Cutoff Open
        </span>
    @endif
</div>
                                    </div>
                                    <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-3 {{ $isPast ? 'text-muted' : 'text-ink' }}">
                                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Exam days</dt><dd class="tabular-nums">{{ $period->starts_on->format('D, M j') }}@unless ($sameDay) – {{ $period->ends_on->format($period->ends_on->isSameMonth($period->starts_on) ? 'D j' : 'D, M j') }}@endunless, {{ $period->ends_on->format('Y') }}</dd></div>
                                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Payment cutoff</dt><dd class="tabular-nums">{{ $period->eligibility_cutoff_on->format('D, M j, Y') }}</dd></div>
                                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-muted">Permits issued</dt><dd class="tabular-nums">@if ($period->issue_opens_on || $period->issue_closes_on){{ $period->issue_opens_on?->format('M j') ?? '—' }} – {{ $period->issue_closes_on?->format('M j, Y') ?? 'open-ended' }}@else<span class="text-muted">Not set</span>@endif</dd></div>
                                    </dl>

                                    @if (auth()->user()?->hasRole('admin'))
                                        <div class="mt-3" x-data="{ editingDeadline: false }">
                                            <button type="button"
                                                @click="editingDeadline = !editingDeadline"
                                                class="inline-flex items-center rounded-lg border border-brand-navy-700 px-3 py-2 text-xs font-bold text-brand-navy-700 hover:bg-brand-blue-100">
                                                Edit / Extend Deadline
                                            </button>

                                            <form
                                                x-show="editingDeadline"
                                                x-cloak
                                                method="POST"
                                                action="{{ route('billing.configuration.exam-periods.deadline', $period) }}"
                                                data-ajax
                                                class="mt-3 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-slate-50 p-4">
                                                @csrf
                                                @method('PATCH')

                                                <label class="jca-label">
                                                    New payment cutoff
                                                    <input
                                                        type="date"
                                                        name="eligibility_cutoff_on"
                                                        class="jca-input"
                                                        value="{{ $period->eligibility_cutoff_on->format('Y-m-d') }}"
                                                        max="{{ $period->starts_on->format('Y-m-d') }}"
                                                        required>
                                                </label>

                                                <button type="submit" class="jca-button-primary">
                                                    Save Deadline
                                                </button>

                                                <button type="button"
                                                    @click="editingDeadline = false"
                                                    class="rounded-lg border border-border bg-white px-4 py-2 text-sm font-semibold text-slate-700">
                                                    Cancel
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach
        @endif

        <div class="rounded-xl bg-slate-50">
            <button type="button" class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-left font-bold text-brand-navy-900" @click="adding = ! adding" :aria-expanded="adding.toString()" aria-controls="exam-period-form">
                <span class="inline-flex items-center gap-2"><x-ui.icon name="academics" size="size-4" /> Add an examination period</span>
                <span class="text-sm text-muted" x-text="adding ? 'Hide' : 'Show'">Show</span>
            </button>
            <form id="exam-period-form" method="POST" action="{{ route('billing.configuration.exam-periods') }}" data-ajax x-show="adding" x-cloak class="grid gap-4 border-t border-border p-4 sm:grid-cols-2 xl:grid-cols-3">
                @csrf
                <label class="jca-label">School year<select class="jca-input" name="academic_year_id" x-model="year" required>@foreach ($academicYears as $optionYear)<option value="{{ $optionYear->id }}" @selected($selectedYear?->is($optionYear))>{{ $optionYear->name }}</option>@endforeach</select></label>
                <label class="jca-label">Code<input class="jca-input" name="code" placeholder="e.g. Q1" required></label>
                <label class="jca-label">Exam name<input class="jca-input" name="name" placeholder="e.g. First Quarterly Examination" required></label>
                <label class="jca-label">First exam day<input class="jca-input" type="date" name="starts_on" required></label>
                <label class="jca-label">Last exam day<input class="jca-input" type="date" name="ends_on" required></label>
                <label class="jca-label">Payment cutoff<input class="jca-input" type="date" name="eligibility_cutoff_on" required><span class="mt-1 block text-xs font-normal text-muted">On or before the first exam day.</span></label>
                <label class="jca-label">Permits issued from<input class="jca-input" type="date" name="issue_opens_on"></label>
                <label class="jca-label">Permits issued until<input class="jca-input" type="date" name="issue_closes_on"></label>
                <input type="hidden" name="status" value="planned">
                <button class="jca-button-primary sm:col-span-2 xl:col-span-3" type="submit">Add examination period</button>
            </form>
        </div>
    </div>
</section>
