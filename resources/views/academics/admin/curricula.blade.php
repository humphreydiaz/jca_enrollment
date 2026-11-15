<x-layouts.app title="Academics · Curriculum">

    @php

        $officialKey = \App\Modules\Academics\Support\OfficialClassProgramTemplate::SOURCE_KEY;

        $formYear = old('academic_year_id', $year && in_array($year->status, ['planned', 'open'], true) ? $year->id : null);

        $formYearModel = $years->firstWhere('id', (int) $formYear);

        $suggestedName = $formYearModel ? 'JCA Class Program '.$formYearModel->code : '';

        $visibleGrades = $grade ? $grades->where('id', $grade->id) : $grades;

    @endphp



    <div class="space-y-6">

        <x-academics.tabs active="curriculum" :year="$year"

            description="The class program is set up once per school year: grade subjects, section names, and class times. Publishing it fills that year's sections automatically; teachers are assigned afterwards on the Class schedules tab." />



        @if ($allYears->isNotEmpty())

            <x-academics.year-filter :action="route('academics.admin.curricula.index')" :years="$allYears" :year="$year"

                :current-year="app(\App\Modules\Academics\Support\CurrentAcademicYear::class)->pick($allYears)"

                :grades="$grades" :grade="$grade" />

        @endif



        {{-- ── Versions for the selected year ── --}}

        <section class="jca-card p-5" aria-labelledby="versions-title">

            <div class="flex flex-wrap items-center justify-between gap-3">

                <h2 id="versions-title" class="text-lg font-black text-brand-navy-900">{{ $year ? 'Class program for '.$year->code : 'Class program' }}</h2>

                @if ($yearCurricula->count() > 1)

                    <div class="flex flex-wrap gap-2" aria-label="Choose curriculum version">

                        @foreach ($yearCurricula as $item)

                            <a href="{{ route('academics.admin.curricula.index', ['curriculum' => $item->public_id]) }}#curriculum-details"

                               @if ($selected?->is($item)) aria-current="true" @endif

                               class="rounded-lg border px-3 py-1.5 text-sm font-semibold {{ $selected?->is($item) ? 'border-brand-navy-700 bg-brand-blue-100 text-brand-navy-900' : 'border-border bg-white text-brand-navy-700' }}">

                                Version {{ $item->version }} · {{ ucfirst($item->status) }}

                            </a>

                        @endforeach

                    </div>

                @endif

            </div>

            @if (! $selected)

                <p class="mt-2 text-sm text-muted">{{ $year ? 'This school year has no class program yet.' : 'Create a school year first in the Setup tab.' }} {{ $year && $years->contains('id', $year->id) ? 'Start one below — the JCA default fills every grade level, section, subject, and class time.' : '' }}</p>

            @endif

        </section>



        @can('create', \App\Modules\Academics\Models\Curriculum::class)

            @if ($years->isNotEmpty())

                @php $expanded = ! $selected || old('academic_year_id') || ($year && $selected->academic_year_id !== $year->id); @endphp

                <details id="new-curriculum" class="jca-card scroll-mt-28 p-5" aria-labelledby="new-curriculum-title" @if ($expanded) open @endif>

                    <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">

                        <h2 id="new-curriculum-title" class="inline text-lg font-black text-brand-navy-900">{{ $selected ? 'Start another class program version' : 'Start a class program' }}</h2>

                    </summary>

                    <p class="mt-1 text-sm text-muted">Use JCA's default class program, copy a published year, or start empty. You can review and change everything before publishing.</p>

                    <form method="POST" action="{{ route('academics.admin.curricula.store') }}" class="mt-4 grid gap-4 md:grid-cols-3">

                        @csrf

                        <x-ui.field for="curriculum_year" label="School year">

                            <select id="curriculum_year" name="academic_year_id" required class="jca-input">

                                <option value="">Select year</option>

                                @foreach ($years as $option)<option value="{{ $option->id }}" @selected((string) $formYear === (string) $option->id)>{{ $option->code }}</option>@endforeach

                            </select>

                        </x-ui.field>

                        <x-ui.field for="curriculum_name" label="Version name">

                            <input id="curriculum_name" name="name" value="{{ old('name', $suggestedName) }}" required maxlength="150" class="jca-input" placeholder="JCA Class Program 2027-2028">

                        </x-ui.field>

                        <x-ui.field for="source_curriculum" label="Start from">

                            <select id="source_curriculum" name="source_curriculum_id" class="jca-input">

                                <option value="{{ $officialKey }}" @selected(old('source_curriculum_id', $defaultSource) === $officialKey)>Default curriculum (JCA class program)</option>

                                @foreach ($curricula->where('status', 'published') as $source)

                                    <option value="{{ $source->id }}" @selected((string) old('source_curriculum_id', $defaultSource) === (string) $source->id)>Copy {{ $source->academicYear->code }} · version {{ $source->version }}</option>

                                @endforeach

                                <option value="" @selected(old('source_curriculum_id', $defaultSource) === '')>Start empty</option>

                            </select>

                        </x-ui.field>

                        <div class="md:col-span-3"><x-ui.button type="submit">Create draft</x-ui.button></div>

                    </form>

                </details>

            @endif

        @endcan



        <section id="curriculum-details" class="scroll-mt-28 space-y-4" aria-label="Curriculum version">

            @if ($selected)

                <div class="jca-card p-5">

                    <div class="flex flex-wrap items-start justify-between gap-4">

                        <div>

                            <p class="text-xs font-bold uppercase tracking-wider text-brand-orange-500">{{ $selected->academicYear->code }} · version {{ $selected->version }}</p>

                            <h2 class="mt-1 text-xl font-black text-brand-navy-900">{{ $selected->name }}</h2>

                            @if ($selected->source_note)<p class="mt-1 text-sm text-muted">{{ $selected->source_note }}</p>@endif

                            <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted">

                                <x-ui.status-badge :status="$selected->status" />

                                {{ $selected->sectionTemplates->count() }} sections · {{ $selected->gradeSubjects->count() }} grade subjects

                            </p>

                        </div>

                        @can('publish', $selected)

                            <form method="POST" action="{{ route('academics.admin.curricula.publish', $selected) }}">

                                @csrf

                                <x-ui.button type="submit">Publish and load Academics</x-ui.button>

                            </form>

                        @endcan

                        @if ($selected->status === 'published')

                            <a href="{{ route('academics.admin.index', ['year' => $selected->academicYear->public_id]) }}" class="jca-button jca-button-secondary">Open class schedules</a>

                        @endif

                    </div>

                    @if ($selected->status === 'draft')

                        <p class="mt-4 rounded-lg bg-brand-blue-100 p-3 text-sm text-brand-navy-900">Review the class times before publishing. Publishing creates any missing sections and subjects for {{ $selected->academicYear->code }}; it never erases existing records or assigns teachers. After publishing, sections can still be changed one at a time on the Class schedules tab.</p>

                    @else

                        <p class="mt-4 rounded-lg bg-canvas p-3 text-sm text-muted">A published class program is kept as history and can't be edited. Change a single section on the Class schedules tab, or start next year's version from this one.</p>

                    @endif

                    @can('delete', $selected)

                        <details class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3">

                            <summary class="jca-button jca-button-danger w-fit cursor-pointer list-none [&::-webkit-details-marker]:hidden">Delete draft</summary>

                            <form method="POST" action="{{ route('academics.admin.curricula.destroy', $selected) }}" class="mt-3 space-y-3">

                                @csrf @method('DELETE')

                                <p class="text-sm text-brand-red-600">This permanently removes {{ $selected->academicYear->code }} version {{ $selected->version }}, its draft subject mappings, section templates, and class times. Published curricula and actual school-year sections are not removed.</p>

                                <label class="jca-check">

                                    <input type="checkbox" name="confirm_deletion" value="1" required>

                                    I understand this draft will be permanently deleted.

                                </label>

                                <x-ui.button type="submit" variant="danger">Confirm delete draft</x-ui.button>

                            </form>

                        </details>

                    @endcan

                </div>



                @can('update', $selected)

                    <div class="grid gap-4 xl:grid-cols-2">

                        <form method="POST" action="{{ route('academics.admin.curricula.subjects.store', $selected) }}" class="jca-card space-y-3 p-5">

                            @csrf

                            <h3 class="font-bold text-brand-navy-900">Add a subject to a grade level</h3>

                            <div class="grid gap-3 sm:grid-cols-2">

                                <x-ui.field for="map_grade" label="Grade level"><select id="map_grade" name="grade_level_id" required class="jca-input"><option value="">Choose grade</option>@foreach($grades->where('school_stage', '!=', 'Pre-elementary') as $option)<option value="{{ $option->id }}" @selected($grade?->is($option))>{{ $option->name }}</option>@endforeach</select></x-ui.field>

                                <x-ui.field for="map_subject" label="Subject"><select id="map_subject" name="subject_id" required class="jca-input"><option value="">Choose subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></x-ui.field>

                            </div>

                            <x-ui.button type="submit" variant="secondary">Add subject</x-ui.button>

                        </form>

                        <form method="POST" action="{{ route('academics.admin.curricula.sections.store', $selected) }}" class="jca-card space-y-3 p-5">

                            @csrf

                            <h3 class="font-bold text-brand-navy-900">Add or edit a section</h3>

                            <div class="grid gap-3 sm:grid-cols-3">

                                <x-ui.field for="template_grade" label="Grade level"><select id="template_grade" name="grade_level_id" required class="jca-input"><option value="">Choose grade</option>@foreach($grades as $option)<option value="{{ $option->id }}" @selected($grade?->is($option))>{{ $option->name }}</option>@endforeach</select></x-ui.field>

                                <x-ui.field for="template_name" label="Section name"><input id="template_name" name="name" required maxlength="100" class="jca-input" /></x-ui.field>

                                <x-ui.field for="template_capacity" label="Capacity"><input id="template_capacity" name="capacity" type="number" min="1" max="100" value="15" required class="jca-input" /></x-ui.field>

                            </div>

                            <x-ui.field for="template_activity" label="Activity program (optional)"><textarea id="template_activity" name="activity_schedule" rows="2" class="jca-input"></textarea></x-ui.field>

                            <x-ui.button type="submit" variant="secondary">Save section</x-ui.button>

                        </form>

                    </div>

                @endcan



                {{-- One full-width table per grade; one row per section (including Nursery/Kindergarten). --}}
                @foreach ($visibleGrades as $gradeItem)
                    @php
                        $gradeMappings = $selected->gradeSubjects
                            ->where('grade_level_id', $gradeItem->id)
                            ->sortBy('sort_order');
                        $gradeSections = $selected->sectionTemplates
                            ->where('grade_level_id', $gradeItem->id)
                            ->sortBy('sort_order');
                    @endphp

                    @if ($gradeMappings->isNotEmpty() || $gradeSections->isNotEmpty())
                        <section class="jca-card p-5 sm:p-6" aria-labelledby="curriculum-grade-{{ $gradeItem->id }}">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                                <h3 id="curriculum-grade-{{ $gradeItem->id }}" class="text-lg font-black text-brand-navy-900">{{ $gradeItem->name }}</h3>
                                <p class="text-base text-brand-navy-900">
                                    {{ $gradeSections->count() }} {{ Str::plural('section', $gradeSections->count()) }}
                                    &middot;
                                    {{ $gradeMappings->count() }} {{ Str::plural('subject', $gradeMappings->count()) }}
                                </p>
                            </div>

                            @if ($gradeMappings->isNotEmpty())
                                <details class="mt-4 rounded-lg border border-border bg-canvas px-3 py-2">
                                    <summary class="cursor-pointer text-base font-bold text-brand-navy-900">Grade-level subjects ({{ $gradeMappings->count() }})</summary>
                                    <ul class="mt-3 flex flex-wrap gap-2" aria-label="{{ $gradeItem->name }} subjects">
                                        @foreach ($gradeMappings as $mapping)
                                            <li class="inline-flex items-center gap-2 rounded-md border border-border bg-white px-3 py-2 text-base text-brand-navy-900">
                                                {{ $mapping->subject->name }}
                                                @can('update', $selected)
                                                    <form method="POST" action="{{ route('academics.admin.curricula.subjects.destroy', [$selected, $mapping->id]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" aria-label="Remove {{ $mapping->subject->name }} from {{ $gradeItem->name }}" class="font-bold text-brand-red-600">&times;</button>
                                                    </form>
                                                @endcan
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <p class="mt-3 text-base text-brand-navy-900">Preschool activity program; no numeric subject curriculum is assumed.</p>
                            @endif

                            <div class="mt-4 overflow-x-auto rounded-lg border border-border">
                                <table class="w-full min-w-[740px] border-collapse text-left text-base font-semibold">
                                    <caption class="sr-only">{{ $gradeItem->name }} sections and schedules</caption>
                                    <thead class="bg-canvas text-base font-bold uppercase tracking-wide text-brand-navy-900">
                                        <tr>
                                            <th scope="col" class="w-36 px-4 py-3">Section</th>
                                            <th scope="col" class="w-24 px-4 py-3">Capacity</th>
                                            <th scope="col" class="px-4 py-3">Class times / activity program</th>
                                            <th scope="col" class="w-36 px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border bg-white">
                                        @forelse ($gradeSections as $template)
                                            @php
                                                $sectionSchedules = $template->schedules
                                                    ->sortBy(fn ($row) => $row->classSchedule()?->startsAt ?? '99');
                                                $scheduledSubjectIds = $sectionSchedules->pluck('subject_id')->all();
                                                $unscheduledMappings = $gradeMappings
                                                    ->filter(fn ($mapping) => ! in_array($mapping->subject_id, $scheduledSubjectIds));
                                            @endphp
                                            <tr class="align-top hover:bg-canvas/40">
                                                <th scope="row" class="px-4 py-4 font-bold text-brand-navy-900">{{ $template->name }}</th>
                                                <td class="px-4 py-4 tabular-nums text-brand-navy-900">{{ $template->capacity }}</td>
                                                <td class="px-4 py-4">
                                                    @if ($gradeMappings->isEmpty())
                                                        {{-- Preschool has activity programs, not numeric subjects. --}}
                                                        @if ($template->activity_schedule)
                                                            {{-- Same timetable component as the Section page. --}}
                                                            <x-academics.activity-program-table :schedule="$template->activity_schedule" :name="$template->name" />
                                                        @else
                                                            <span class="text-brand-navy-900">No activity program recorded yet.</span>
                                                        @endif
                                                    @else
                                                        @if ($template->activity_schedule)
                                                            <p class="mb-3 whitespace-pre-line break-words text-brand-navy-900">{{ $template->activity_schedule }}</p>
                                                        @endif
                                                        <div class="overflow-x-auto rounded-md border border-border">
                                                            <table class="w-full min-w-[570px] text-left text-base">
                                                                <thead class="bg-canvas font-bold text-brand-navy-900">
                                                                    <tr>
                                                                        <th class="px-3 py-2">Subject</th>
                                                                        <th class="px-3 py-2">Days</th>
                                                                        <th class="px-3 py-2">Time</th>
                                                                        <th class="px-3 py-2">Teacher</th>
                                                                        <th class="px-3 py-2 text-right">Edit</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-border">
                                                                    @foreach ($sectionSchedules as $schedule)
                                                                        @php $time = $schedule->classSchedule(); @endphp
                                                                        <tr>
                                                                            <td class="px-3 py-2 font-bold text-brand-navy-900">{{ $schedule->subject->name }}</td>
                                                                            <td class="px-3 py-2 text-brand-navy-900">{{ $time?->daysLabel() ?? '—' }}</td>
                                                                            <td class="px-3 py-2 tabular-nums text-brand-navy-900">{{ $time ? $time->startsAt.' – '.$time->endsAt : ($schedule->schedule_text ?: 'Time to confirm') }}</td>
                                                                            <td class="px-3 py-2 text-brand-navy-900">—</td>
                                                                            <td class="px-3 py-2 text-right">
                                                                                @if ($selected->status === 'published')
                                                                                    <a href="{{ route('academics.admin.index', ['year' => $selected->academicYear->public_id]) }}" class="font-bold text-brand-navy-900 hover:underline">Class schedules</a>
                                                                                @else
                                                                                    @can('update', $selected)
                                                                                        <a href="#curriculum-time-{{ $template->id }}"
                                                                                           onclick="document.getElementById('curriculum-time-{{ $template->id }}').open = true; document.getElementById('schedule_subject_{{ $template->id }}').value = '{{ (int) $schedule->subject_id }}';"
                                                                                           class="font-bold text-brand-navy-900 hover:underline">Set time</a>
                                                                                    @else
                                                                                        <span class="text-brand-navy-900">—</span>
                                                                                    @endcan
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                    @foreach ($unscheduledMappings as $mapping)
                                                                        <tr>
                                                                            <td class="px-3 py-2 font-bold text-brand-navy-900">{{ $mapping->subject->name }}</td>
                                                                            <td class="px-3 py-2 text-brand-navy-900">—</td>
                                                                            <td class="px-3 py-2 text-brand-navy-900">Time to confirm</td>
                                                                            <td class="px-3 py-2 text-brand-navy-900">—</td>
                                                                            <td class="px-3 py-2 text-right">
                                                                                @can('update', $selected)
                                                                                    <a href="#curriculum-time-{{ $template->id }}"
                                                                                       onclick="document.getElementById('curriculum-time-{{ $template->id }}').open = true; document.getElementById('schedule_subject_{{ $template->id }}').value = '{{ (int) $mapping->subject_id }}';"
                                                                                       class="font-bold text-brand-navy-900 hover:underline">Set time</a>
                                                                                @else
                                                                                    <span class="text-brand-navy-900">Not scheduled</span>
                                                                                @endcan
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <p class="mt-2 text-base text-brand-navy-900">Teacher assignments are handled on the Class schedules tab after publishing.</p>

                                                        @can('update', $selected)
                                                            <details id="curriculum-time-{{ $template->id }}" class="mt-3 scroll-mt-28 rounded-lg border border-dashed border-border p-3">
                                                                <summary class="cursor-pointer text-base font-bold text-brand-navy-900">+ Set a class time for {{ $template->name }}</summary>
                                                                <form method="POST" action="{{ route('academics.admin.curricula.schedules.store', [$selected, $template]) }}" class="mt-3 space-y-3">
                                                                    @csrf
                                                                    <x-ui.field for="schedule_subject_{{ $template->id }}" label="Subject">
                                                                        <select id="schedule_subject_{{ $template->id }}" name="subject_id" required class="jca-input">
                                                                            @foreach ($gradeMappings as $mapping)
                                                                                <option value="{{ $mapping->subject_id }}">{{ $mapping->subject->name }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </x-ui.field>
                                                                    <x-academics.schedule-fields :id-prefix="'template_'.$template->id" compact />
                                                                    <x-ui.button type="submit" variant="secondary">Save class time</x-ui.button>
                                                                </form>
                                                            </details>
                                                        @endcan
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-right">
                                                    @can('update', $selected)
                                                        <form method="POST" action="{{ route('academics.admin.curricula.sections.destroy', [$selected, $template]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-base font-bold text-brand-red-600 hover:underline" aria-label="Remove {{ $template->name }} section">Remove section</button>
                                                        </form>
                                                    @else
                                                        <span class="text-brand-navy-900">—</span>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-6 text-center text-base text-brand-navy-900">There are no sections for this grade level yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif
                @endforeach

            @elseif ($allYears->isEmpty())

                <x-ui.empty-state icon="academics" title="No school year yet" description="Create the first school year in the Setup tab, then come back here to load its class program." />

            @endif

        </section>

    </div>

</x-layouts.app>
