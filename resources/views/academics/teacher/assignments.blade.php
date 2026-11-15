<x-layouts.app title="My teaching">
    @php($firstSection = $sections->first()['section'] ?? null)
    <div class="space-y-6" x-data="teacherWorkspace(@js($firstSection?->public_id))">
        <x-ui.page-header
            eyebrow="Teacher workspace"
            title="My teaching"
            description="Your sections, class times, and students, one school year at a time. Select a student to see their classroom support notes.">
            <x-slot:actions>
                <a href="{{ route('grading.teacher.index', $year ? ['year' => $year->public_id] : []) }}" class="jca-button-secondary inline-flex items-center gap-2">
                    <x-ui.icon name="academics" size="size-4" /> Enter grades
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        <x-teaching.year-tabs :years="$years" :year="$year" route="academics.teacher.assignments" />

        @if ($sections->isEmpty())
            <div class="jca-card p-6">
                <x-ui.empty-state icon="teaching" title="No active teaching assignments" description="When the Admin assigns you as an adviser or subject teacher, your sections and students appear here." />
            </div>
        @else
            {{-- At-a-glance totals for the selected year --}}
            <dl class="grid grid-cols-3 gap-2 sm:gap-3">
                @foreach ([['Sections', $totals['sections'], 'sections'], ['Subject classes', $totals['classes'], 'teaching'], ['Students', $totals['students'], 'people']] as [$label, $value, $icon])
                    <div class="jca-card flex items-center gap-4 p-3 sm:p-4">
                        <span class="hidden size-11 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 sm:grid"><x-ui.icon :name="$icon" /></span>
                        <div class="min-w-0">
                            <dt class="text-xs font-extrabold uppercase tracking-wider text-muted sm:text-xs">{{ $label }}</dt>
                            <dd class="text-xl font-black tabular-nums text-brand-navy-900 sm:text-2xl">{{ $value }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>

            {{-- Section switcher: anchors without JavaScript, one section at a time with it --}}
            @if ($sections->count() > 1)
                <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Your sections in {{ $year->code }}">
                    @foreach ($sections as $entry)
                        @php($id = $entry['section']->public_id)
                        <a href="#section-{{ $id }}"
                           @click.prevent="activeSection = '{{ $id }}'"
                           :aria-current="activeSection === '{{ $id }}' ? 'true' : null"
                           :class="activeSection === '{{ $id }}' ? 'border-brand-navy-700 bg-brand-navy-700 text-white' : 'border-border bg-white text-brand-navy-900 hover:border-brand-navy-700'"
                           class="shrink-0 rounded-xl border px-4 py-2.5 text-left shadow-sm transition-colors">
                            <span class="block text-sm font-black">{{ $entry['section']->gradeLevel->name }} · {{ $entry['section']->name }}</span>
                            <span class="block text-xs font-semibold opacity-80">{{ collect([$entry['isAdviser'] ? 'Adviser' : null, $entry['subjectRoles']->count() ? $entry['subjectRoles']->count().' '.Str::plural('subject', $entry['subjectRoles']->count()) : null])->filter()->join(' · ') }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif

            @foreach ($sections as $entry)
                @php($section = $entry['section'])
                @php($offerings = $section->sectionSubjects->sortBy(fn ($offering) => ($offering->classSchedule()?->startsAt ?? '99:99').'-'.$offering->subject->name)->values())
                @php($numeric = \App\Modules\Grading\Support\NumericGradeLevels::includes($section->gradeLevel))
                <article id="section-{{ $section->public_id }}" x-show="!activeSection || activeSection === '{{ $section->public_id }}'"
                         class="jca-card scroll-mt-28 overflow-hidden" aria-labelledby="section-title-{{ $section->public_id }}">
                    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4 sm:px-6">
                        <div>
                            <h2 id="section-title-{{ $section->public_id }}" class="text-xl font-black text-brand-navy-900">{{ $section->gradeLevel->name }} · {{ $section->name }}</h2>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @if ($entry['isAdviser'])
                                    <span class="rounded-full bg-brand-gold-500/25 px-2.5 py-0.5 text-xs font-extrabold text-brand-navy-900">Adviser</span>
                                @endif
                                @foreach ($entry['subjectRoles'] as $role)
                                    <span class="rounded-full bg-brand-blue-100 px-2.5 py-0.5 text-xs font-bold text-brand-navy-700">{{ $role->sectionSubject->subject->name }}</span>
                                @endforeach
                            </div>
                        </div>
                        <p class="text-sm font-bold text-muted">{{ $entry['students']->count() }} {{ Str::plural('student', $entry['students']->count()) }}</p>
                    </header>

                    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.6fr)_minmax(18rem,1fr)] lg:divide-x lg:divide-border">
                        {{-- Class schedule --}}
                        <section class="p-5 sm:p-6" aria-labelledby="schedule-title-{{ $section->public_id }}">
                            <h3 id="schedule-title-{{ $section->public_id }}" class="text-sm font-extrabold uppercase tracking-wider text-brand-navy-700">Class schedule</h3>
                            @if ($offerings->isEmpty() && $section->activity_schedule)
                                <x-academics.daily-activities :section="$section" class="mt-3 rounded-xl border border-border p-4" />
                            @elseif ($offerings->isEmpty())
                                <p class="mt-3 rounded-xl border border-dashed border-border p-4 text-sm text-muted">No subjects have been scheduled for this section yet.</p>
                            @else
                                <div class="mt-3 overflow-hidden rounded-xl border border-border">
                                    <div class="hidden grid-cols-[minmax(0,1fr)_5.5rem_8rem_minmax(7rem,9rem)] gap-3 bg-canvas px-4 py-2.5 text-xs font-extrabold uppercase tracking-wider text-muted md:grid" aria-hidden="true">
                                        <span>Subject</span><span>Days</span><span>Time</span><span>Teacher</span>
                                    </div>
                                    <ul class="divide-y divide-border md:border-t md:border-border">
                                        @foreach ($offerings as $offering)
                                            @php($schedule = $offering->classSchedule())
                                            @php($yours = in_array($offering->id, $entry['yourOfferingIds'], true))
                                            @php($role = $yours ? $entry['subjectRoles']->firstWhere('section_subject_id', $offering->id) : null)
                                            <li class="grid gap-1 px-4 py-3 text-sm md:grid-cols-[minmax(0,1fr)_5.5rem_8rem_minmax(7rem,9rem)] md:items-center md:gap-3 md:py-2.5 {{ $yours ? 'bg-brand-blue-100/50 shadow-[inset_3px_0_0_var(--color-brand-navy-700)]' : '' }}">
                                                <p class="font-bold text-brand-navy-900">
                                                    {{ $offering->subject->name }}
                                                    @if ($yours)
                                                        <span class="sr-only"> (Your class)</span>
                                                    @endif
                                                </p>
                                                @if ($schedule)
                                                    <p class="text-muted md:text-ink"><span class="sr-only">Days: </span>{{ $schedule->daysLabel() }}<span class="md:hidden"> · {{ $schedule->startsAt }} – {{ $schedule->endsAt }}</span></p>
                                                    <p class="hidden tabular-nums md:block"><span class="sr-only">Time: </span>{{ $schedule->startsAt }} – {{ $schedule->endsAt }}</p>
                                                @else
                                                    <p class="text-muted md:col-span-2">{{ $offering->schedule_text ?: 'Time not set yet' }}</p>
                                                @endif
                                                <p>
                                                    <span class="sr-only">Teacher: </span>
                                                    @if ($yours)
                                                        <span class="inline-flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                                            <span class="font-semibold text-brand-navy-900">You</span>
                                                            @if ($numeric && $role)
                                                                <a href="{{ route('grading.teacher.show', $role) }}" class="whitespace-nowrap font-bold text-brand-navy-700 underline-offset-2 hover:underline">Enter grades<span class="sr-only"> for {{ $offering->subject->name }}</span> →</a>
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span class="{{ $entry['teachers']->get($offering->id) ? 'text-ink' : 'text-muted' }}">{{ $entry['teachers']->get($offering->id) ?: 'Not assigned' }}</span>
                                                    @endif
                                                </p>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </section>

                        {{-- Students --}}
                        <section class="border-t border-border p-5 sm:p-6 lg:border-t-0" aria-labelledby="students-title-{{ $section->public_id }}">
                            <div class="flex items-baseline justify-between gap-2">
                                <h3 id="students-title-{{ $section->public_id }}" class="text-sm font-extrabold uppercase tracking-wider text-brand-navy-700">Students</h3>
                                @php($supportCount = $entry['students']->where('hasSupport', true)->count())
                                @if ($supportCount)
                                    <p class="text-xs font-semibold text-muted">{{ $supportCount }} with support notes</p>
                                @endif
                            </div>
                            @if ($entry['students']->isEmpty())
                                <p class="mt-3 rounded-xl border border-dashed border-border p-4 text-sm text-muted">No students are confirmed in this section yet.</p>
                            @else
                                <ol class="mt-3 divide-y divide-border overflow-hidden rounded-xl border border-border">
                                    @foreach ($entry['students'] as $index => $row)
                                        @php($student = $row['student'])
                                        <li>
                                            <a href="{{ route('teacher.students.support-summary', $student) }}"
                                               @click="openStudent($event, @js($student->full_name))"
                                               aria-haspopup="dialog"
                                               class="group flex items-center gap-3 px-3 py-2.5 hover:bg-canvas focus-visible:bg-canvas">
                                                <span class="w-6 shrink-0 text-right text-xs font-bold tabular-nums text-muted">{{ $index + 1 }}</span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block break-words font-bold text-brand-navy-900 group-hover:underline">{{ $student->last_name }}, {{ collect([$student->first_name, $student->middle_name ? mb_substr($student->middle_name, 0, 1).'.' : null, $student->extension_name])->filter()->join(' ') }}</span>
                                                    <span class="block text-xs tabular-nums text-muted">{{ $student->student_number ?? 'No student ID yet' }}</span>
                                                </span>
                                                @if ($row['hasSupport'])
                                                    <span class="shrink-0 rounded-full bg-warning-100 px-2 py-0.5 text-xs font-extrabold text-warning-800">Support notes</span>
                                                @endif
                                                <x-ui.icon name="arrow-right" size="size-4" class="shrink-0 text-muted group-hover:text-brand-navy-700" />
                                            </a>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </section>
                    </div>
                </article>
            @endforeach
        @endif

        {{-- Student summary dialog (content is fetched and logged only when a student is opened) --}}
        <dialog x-ref="studentDialog" @close="closeStudent()" @click.self="$refs.studentDialog.close()"
                class="m-auto w-[min(36rem,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55"
                aria-labelledby="student-dialog-title">
            <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-white px-5 py-4 sm:px-6">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Student summary</p>
                    <h2 id="student-dialog-title" class="mt-1 text-xl font-black text-brand-navy-900" x-text="studentName"></h2>
                </div>
                <button type="button" class="grid size-10 place-items-center rounded-lg text-muted hover:bg-slate-100" @click="$refs.studentDialog.close()" aria-label="Close student summary"><x-ui.icon name="close" /></button>
            </header>
            <div class="p-5 sm:p-6" aria-live="polite">
                <p x-show="studentLoading" class="py-8 text-center text-sm font-semibold text-muted">Loading…</p>
                <p x-show="studentError" class="rounded-xl bg-warning-50 p-4 text-sm font-semibold text-warning-800" x-text="studentError"></p>
                <div x-show="!studentLoading && !studentError" x-html="studentHtml"></div>
            </div>
            <footer class="flex justify-end border-t border-border px-5 py-4 sm:px-6">
                <button type="button" class="jca-button-secondary" @click="$refs.studentDialog.close()">Close</button>
            </footer>
        </dialog>
    </div>
</x-layouts.app>
