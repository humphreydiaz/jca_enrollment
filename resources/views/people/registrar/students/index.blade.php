<x-layouts.app title="Student directory">
    @php
        $currentSort = in_array(request('sort'), $sortOptions, true) ? request('sort') : 'name';
        $currentDirection = request('direction') === 'desc' ? 'desc' : 'asc';
        $sortUrl = fn (string $column) => request()->fullUrlWithQuery([
            'sort' => $column,
            'direction' => $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc',
            'page' => null,
        ]);
        $hasFilters = request()->hasAny(['search', 'year', 'grade', 'status', 'flag']);
    @endphp

    <div class="space-y-6" x-data="studentDirectory()">

        {{-- ── Page header ── --}}
        <x-ui.page-header
            title="Student directory"
            description="Every learner profile on record. Search, filter, and open a learner to read the full application detail — background, household, guardians, and enrollment history."
            eyebrow="Registrar workspace"
        />

        {{-- ── Search & filter bar ── --}}
        <section class="jca-card overflow-hidden" aria-label="Filter learners">
            <form method="GET" class="grid gap-3 p-4 sm:p-5 md:grid-cols-2 xl:grid-cols-[minmax(14rem,2fr)_repeat(4,minmax(8rem,1fr))_auto]">
                <input type="hidden" name="sort" value="{{ $currentSort }}">
                <input type="hidden" name="direction" value="{{ $currentDirection }}">

                <label class="jca-label">Search learners
                    <input name="search" value="{{ request('search') }}" placeholder="Name, LRN, student or reference number…" class="jca-input" />
                </label>
                <label class="jca-label">School year
                    <select name="year" class="jca-input">
                        <option value="">All years</option>
                        @foreach($filterYears as $year)
                            <option value="{{ $year->public_id }}" @selected(request('year') === $year->public_id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="jca-label">Grade level
                    <select name="grade" class="jca-input">
                        <option value="">All grades</option>
                        @foreach($filterGrades as $grade)
                            <option value="{{ $grade->public_id }}" @selected(request('grade') === $grade->public_id)>{{ $grade->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="jca-label">Enrollment status
                    <select name="status" class="jca-input">
                        <option value="">Any status</option>
                        @foreach($filterStatuses as $filterStatus)
                            <option value="{{ $filterStatus }}" @selected(request('status') === $filterStatus)>{{ str($filterStatus)->replace('_', ' ')->headline() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="jca-label">Learner flag
                    <select name="flag" class="jca-input">
                        <option value="">No flag filter</option>
                        @foreach($filterFlags as $flagKey => $flagLabel)
                            <option value="{{ $flagKey }}" @selected(request('flag') === $flagKey)>{{ $flagLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <x-ui.button type="submit" variant="primary" class="w-full justify-center">
                        <x-ui.icon name="search" size="size-4" />
                        Filter
                    </x-ui.button>
                    @if($hasFilters)
                        <a href="{{ route('people.registrar.students.index') }}" class="jca-button jca-button-secondary whitespace-nowrap">Clear</a>
                    @endif
                </div>
            </form>
        </section>

        {{-- ── Results table ── --}}
        <section class="jca-card overflow-hidden" aria-label="Learner profiles">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-4 sm:px-6">
                <p class="text-sm text-muted">
                    <span class="font-bold text-ink">{{ $students->total() }}</span>
                    {{ Str::plural('learner', $students->total()) }} found
                    @if($students->hasPages())
                        · Showing {{ $students->firstItem() }}–{{ $students->lastItem() }}
                    @endif
                </p>
                <p class="text-xs text-muted">Opening a record is recorded in the audit trail.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            @foreach([
                                ['label' => 'Learner', 'sort' => 'name'],
                                ['label' => 'LRN', 'sort' => 'lrn'],
                                ['label' => 'Student number', 'sort' => 'student_number'],
                                ['label' => 'Date of birth', 'sort' => 'birth_date'],
                            ] as $column)
                                <th class="px-5 py-3" aria-sort="{{ $currentSort === $column['sort'] ? ($currentDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <a href="{{ $sortUrl($column['sort']) }}" class="inline-flex items-center gap-1 hover:text-brand-navy-900">
                                        {{ $column['label'] }}
                                        @if($currentSort === $column['sort'])
                                            <x-ui.icon name="arrow-right" size="size-3" class="{{ $currentDirection === 'asc' ? '-rotate-90' : 'rotate-90' }}" />
                                        @endif
                                    </a>
                                </th>
                            @endforeach
                            <th class="px-5 py-3">Latest application</th>
                            <th class="px-5 py-3">Flags</th>
                            <th class="px-5 py-3 text-right">Record</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-white">
                        @forelse($students as $student)
                            @php
                                $recordUrl = route('people.registrar.students.show', $student);
                                $latest = $student->latestEnrollment;
                            @endphp
                            <tr
                                class="cursor-pointer transition-colors hover:bg-slate-50/60"
                                @click="open(@js($recordUrl), @js($student->full_name))"
                            >
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-blue-100 text-sm font-black text-brand-navy-700">
                                            {{ strtoupper(substr($student->first_name ?? 'S', 0, 1)) }}{{ strtoupper(substr($student->last_name ?? '', 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-bold text-brand-navy-900">{{ $student->full_name }}</p>
                                            <p class="mt-0.5 text-xs text-muted">
                                                {{ $student->sex ? ucfirst($student->sex) : 'Sex not recorded' }}
                                                · {{ $student->enrollments_count }} {{ Str::plural('application', $student->enrollments_count) }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 tabular-nums">{{ $student->lrn ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $student->student_number ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-muted">{{ $student->birth_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-4">
                                    @if($latest)
                                        <p class="font-semibold">{{ $latest->gradeLevel?->name ?? '—' }}</p>
                                        <p class="mt-1 flex items-center gap-2 text-xs text-muted">
                                            {{ $latest->academicYear?->name ?? '—' }}
                                            <x-ui.status-badge :status="$latest->status" />
                                        </p>
                                    @else
                                        <span class="text-xs text-muted">No application</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if(filled($student->ip_group))
                                            <x-ui.status-badge label="IP" tone="info" />
                                        @endif
                                        @if($student->is_4ps_recipient)
                                            <x-ui.status-badge label="4Ps" tone="info" />
                                        @endif
                                        @if($student->is_learner_with_disability)
                                            <x-ui.status-badge label="LWD" tone="warning" />
                                        @endif
                                        @if(blank($student->ip_group) && ! $student->is_4ps_recipient && ! $student->is_learner_with_disability)
                                            <span class="text-xs text-muted">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a
                                        href="{{ $recordUrl }}"
                                        class="jca-button-secondary whitespace-nowrap"
                                        @click.prevent.stop="open(@js($recordUrl), @js($student->full_name))"
                                    >
                                        View record
                                        <x-ui.icon name="arrow-right" size="size-4" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8">
                                    <x-ui.empty-state
                                        icon="people"
                                        title="No learner matches these filters"
                                        description="Adjust the search terms or clear the filters. Learner profiles are created when an enrollment application is submitted."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($students->hasPages())
                <div class="border-t border-border px-5 py-4 sm:px-6">
                    {{ $students->onEachSide(1)->links() }}
                </div>
            @endif
        </section>

        {{-- ── Learner record dialog ── --}}
        <dialog
            x-ref="recordDialog"
            @cancel="reset()"
            @close="reset()"
            class="m-auto w-[min(76rem,calc(100%-2rem))] overflow-hidden rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/60"
            aria-labelledby="student-record-title"
        >
            <div class="flex max-h-[min(56rem,calc(100vh-3rem))] flex-col">
                <header class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-extrabold uppercase tracking-wider text-brand-orange-500">Learner record</p>
                        <h2 id="student-record-title" class="mt-1 truncate text-lg font-black text-brand-navy-900" x-text="recordName"></h2>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a :href="recordUrl" class="jca-button-secondary whitespace-nowrap">Open full page</a>
                        <button type="button" class="grid size-10 place-items-center rounded-lg text-muted hover:bg-slate-100" @click="$refs.recordDialog.close()" aria-label="Close learner record">
                            <x-ui.icon name="close" />
                        </button>
                    </div>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto bg-canvas/40 px-5 py-5">
                    <p x-show="loading" class="py-10 text-center text-sm text-muted">Loading learner record…</p>
                    <p x-show="error" x-cloak class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-8 text-center text-sm text-brand-red-600" x-text="error"></p>
                    <div x-show="! loading && ! error" x-html="recordHtml"></div>
                </div>
            </div>
        </dialog>
    </div>
</x-layouts.app>
