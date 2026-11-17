@once
<style>
/* JCA preschool white background */
div:has(> .jca-preschool-table) {
    background-color: #ffffff !important;
}
</style>
@endonce
@once
<style>
.jca-preschool-table {
    width: 100%;
    display: block;
}

.jca-preschool-table table {
    width: 100% !important;
    min-width: 0 !important;
    border-collapse: collapse !important;
    background-color: #ffffff !important;
    border: 1px solid #d5dae5 !important;
}

.jca-preschool-table th {
    background-color: #f5f6fa !important;
    color: #102348 !important;
    font-size: 16px !important;
    font-weight: 900 !important;
    padding: 14px 16px !important;
    border: 1px solid #d5dae5 !important;
}

.jca-preschool-table td {
    color: #102348 !important;
    font-size: 16px !important;
    font-weight: 400 !important;
    padding: 12px 16px !important;
    border: 1px solid #d5dae5 !important;
    line-height: 1.6 !important;
}

.jca-preschool-table tbody tr:nth-child(even) {
    background-color: #ffffff !important;
}

.jca-preschool-table tbody tr:nth-child(odd) {
    background-color: #ffffff !important;
}

.jca-preschool-table th:first-child,
.jca-preschool-table td:first-child {
    text-align: left !important;
    width: 65%;
}

.jca-preschool-table th:last-child,
.jca-preschool-table td:last-child {
    text-align: right !important;
    width: 35%;
    font-variant-numeric: tabular-nums;
}

.jca-preschool-table p,
.jca-preschool-table span {
    font-size: 16px !important;
    font-weight: 400 !important;
    color: #102348 !important;
}
</style>
@endonce
{{-- One section's class program: subjects, class times, adviser, and subject teachers. --}}
@php
    $adviser = $section->teachingAssignments->firstWhere('assignment_type', 'adviser');
    $offerings = $section->allSectionSubjects->where('status', 'active')
        ->sortBy(fn ($offering) => ($offering->classSchedule()?->startsAt ?? '99:99').'-'.$offering->subject->name)->values();
    $archivedOfferings = $section->allSectionSubjects->where('status', 'archived')->values();
    $teachersByOffering = $section->teachingAssignments->where('assignment_type', 'subject_teacher')->groupBy('section_subject_id');
    $isPreschool = $section->gradeLevel->school_stage === 'Pre-elementary';
    $changedFromCurriculum = $section->curriculum_id !== null
        && ($missingCurriculumSubjects > 0 || $archivedOfferings->isNotEmpty() || $section->allSectionSubjects->contains(fn ($offering) => $offering->curriculum_id === null));
    $editable = in_array($year->status, ['planned', 'open'], true);
    $structurallyLocked = $section->curriculum_id !== null || ($sectionUsage['student placements'] ?? 0) > 0 || ($sectionUsage['teaching assignments'] ?? 0) > 0;
    $offeredSubjectIds = $section->allSectionSubjects->pluck('subject_id');
    $backUrl = route('academics.admin.index', [...$yearQuery, 'grade' => $section->gradeLevel->public_id]);
@endphp

<section id="section-details" class="jca-card scroll-mt-28 overflow-hidden" aria-labelledby="section-detail-title">
    <div class="border-b border-border px-5 py-4 sm:px-6">
        <a href="{{ $backUrl }}" class="text-sm font-bold text-brand-navy-700 hover:underline">← All {{ $section->gradeLevel->name }} sections</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 id="section-detail-title" class="text-2xl font-black text-brand-navy-900">{{ $section->gradeLevel->name }} · {{ $section->name }}</h2>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <x-ui.status-badge :status="$section->status === 'active' ? 'active' : 'archived'" />
                    @if ($section->curriculum_id === null)
                        <x-ui.status-badge tone="warning" label="Added manually" />
                    @elseif ($changedFromCurriculum)
                        <x-ui.status-badge tone="info" label="Changed from curriculum" />
                    @else
                        <x-ui.status-badge tone="ready" label="Matches curriculum" />
                    @endif
                    <span class="text-muted">{{ $section->enrolled_count }} of {{ $section->capacity }} enrolled</span>
                </div>
            </div>
            @if ($missingCurriculumSubjects > 0 && $editable)
                <form method="POST" action="{{ route('academics.admin.sections.curriculum-subjects.store', $section) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary">Add {{ $missingCurriculumSubjects }} missing curriculum {{ Str::plural('subject', $missingCurriculumSubjects) }}</x-ui.button>
                </form>
            @endif
        </div>
    </div>

    {{-- ── Adviser ── --}}
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-wider text-muted">Adviser</p>
            <p class="mt-1 font-bold {{ $adviser ? 'text-brand-navy-900' : 'text-warning-800' }}">{{ $adviser?->staffProfile->user->name ?? 'Not assigned yet' }}</p>
        </div>
        @if ($editable)
            <details class="w-full sm:w-auto sm:min-w-[22rem]" @if (old('assignment_type') === 'adviser') open @endif>
                <summary class="jca-button jca-button-secondary w-fit cursor-pointer list-none [&::-webkit-details-marker]:hidden">{{ $adviser ? 'Change adviser' : 'Assign adviser' }}</summary>
                <form method="POST" action="{{ route('academics.admin.teaching-assignments.store') }}" class="mt-3 space-y-3 rounded-xl border border-border bg-canvas/60 p-4">
                    @csrf
                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                    <input type="hidden" name="assignment_type" value="adviser">
                    <input type="hidden" name="is_active" value="1">
                    <input type="hidden" name="replace_existing" value="1">
                    <x-ui.field for="adviser_staff" label="Teacher">
                        <select id="adviser_staff" name="staff_profile_id" required class="jca-input">
                            <option value="">Choose teacher</option>
                            @foreach ($staffProfiles as $staff)
                                <option value="{{ $staff->id }}" @selected($adviser?->staff_profile_id === $staff->id)>{{ $staff->user->name }}{{ $staff->employee_number ? ' · '.$staff->employee_number : '' }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    @if ($adviser)
                        <p class="text-xs text-muted">The current adviser's assignment is deactivated (kept in history) when you save.</p>
                    @endif
                    <x-ui.button type="submit">Save adviser</x-ui.button>
                </form>
            </details>
        @endif
    </div>

    {{-- ── Subjects and class times ── --}}
    <div class="px-5 py-5 sm:px-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-lg font-black text-brand-navy-900">Subjects and class times</h3>
            <p class="text-sm text-muted">{{ $offerings->count() }} {{ Str::plural('subject', $offerings->count()) }}</p>
        </div>

        @if ($isPreschool && $offerings->isEmpty())
            <div class="mt-3 rounded-xl bg-brand-blue-100 p-4 text-sm text-brand-navy-900">
                <p class="text-lg font-black text-brand-navy-900">Preschool activity program</p>
                @if ($section->activity_schedule)
                    <div class="jca-preschool-table w-full overflow-x-auto">
<x-academics.activity-program-table :schedule="$section->activity_schedule" :name="$section->name" class="mt-2" />
</div>
                @else
                    <p class="mt-1">No activity program recorded yet. Add it under “Edit section details”.</p>
                @endif
            </div>
        @elseif ($offerings->isEmpty())
            <x-ui.empty-state class="mt-3" icon="academics" title="No subjects yet" description="Add the missing curriculum subjects above, or add a subject by hand below." />
        @else
            <div class="mt-3 rounded-xl border border-border">
                <div class="hidden grid-cols-[minmax(0,1.6fr)_7rem_8rem_minmax(0,1.3fr)_6rem] gap-3 border-b border-border bg-canvas px-4 py-2.5 text-xs font-extrabold uppercase tracking-wider text-muted md:grid" aria-hidden="true">
                    <span>Subject</span><span>Days</span><span>Time</span><span>Teacher</span><span class="text-right">Actions</span>
                </div>
                <ul class="divide-y divide-border">
                    @foreach ($offerings as $offering)
                        @php
                            $schedule = $offering->classSchedule();
                            $teachers = $teachersByOffering->get($offering->id, collect());
                            $panelId = 'offering-'.$offering->id;
                            $usage = $offeringUsage[$offering->id] ?? [];
                        @endphp
                        @php $startOpen = old('section_subject_id') == $offering->id; @endphp
                        {{-- CSS-only disclosure: the visually hidden checkbox toggles the edit panel, no JavaScript needed. --}}
                        <li class="group/offering">
                            <input type="checkbox" id="toggle-{{ $panelId }}" class="offering-toggle peer sr-only" aria-controls="{{ $panelId }}" @checked($startOpen)>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 md:grid md:grid-cols-[minmax(0,1.6fr)_7rem_8rem_minmax(0,1.3fr)_6rem]">
                                <div class="w-full min-w-0 md:w-auto">
                                    <p class="font-bold text-brand-navy-900">{{ $offering->subject->name }}</p>
                                    <p class="text-xs text-muted">{{ $offering->subject->code }}@if ($offering->curriculum_id === null) · <span class="font-bold text-warning-800">Added manually</span>@endif</p>
                                </div>
                                <p class="text-sm"><span class="font-semibold text-muted md:hidden">Days: </span>{{ $schedule?->daysLabel() ?? '—' }}</p>
                                <p class="text-sm tabular-nums"><span class="font-semibold text-muted md:hidden">Time: </span>{!! $schedule ? e($schedule->startsAt.' – '.$schedule->endsAt) : '<span class="text-warning-800">'.e($offering->schedule_text ?: 'To confirm').'</span>' !!}</p>
                                <p class="text-sm"><span class="font-semibold text-muted md:hidden">Teacher: </span>{!! $teachers->isNotEmpty() ? e($teachers->map(fn ($item) => $item->staffProfile->user->name)->join(', ')) : '<span class="font-semibold text-warning-800">Unassigned</span>' !!}</p>
                                <label for="toggle-{{ $panelId }}" class="ml-auto cursor-pointer select-none rounded-lg px-3 py-1.5 text-sm font-bold text-brand-navy-700 hover:bg-brand-blue-100 group-has-[.offering-toggle:focus-visible]/offering:outline-3 group-has-[.offering-toggle:focus-visible]/offering:outline-brand-gold-500 md:justify-self-end">
                                    <span class="group-has-[.offering-toggle:checked]/offering:hidden">Edit</span><span class="hidden group-has-[.offering-toggle:checked]/offering:inline">Close</span><span class="sr-only"> {{ $offering->subject->name }}</span>
                                </label>
                            </div>
                            <div id="{{ $panelId }}" class="mx-4 mb-4 hidden gap-4 rounded-xl border border-border bg-canvas/60 p-4 peer-checked:grid lg:grid-cols-2">
                                    @if ($editable)
                                        <form method="POST" action="{{ route('academics.admin.section-subjects.update', $offering) }}" class="space-y-3">
                                            @csrf @method('PATCH')
                                            <h4 class="text-sm font-black text-brand-navy-900">Class time</h4>
                                            <input type="hidden" name="section_subject_id" value="{{ $offering->id }}">
                                            <input type="hidden" name="form" value="class_time">
                                            <x-academics.schedule-fields :id-prefix="'sched_'.$offering->id" :record="$offering" compact :use-old="$startOpen && old('form') === 'class_time'" />
                                            @error('starts_at')<p class="text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                                            @error('ends_at')<p class="text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                                            @error('schedule_text')<p class="text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                                            <x-ui.button type="submit" variant="secondary">Save class time</x-ui.button>
                                        </form>
                                        <form method="POST" action="{{ route('academics.admin.teaching-assignments.store') }}" class="space-y-3">
                                            @csrf
                                            <h4 class="text-sm font-black text-brand-navy-900">{{ $teachers->isNotEmpty() ? 'Change teacher' : 'Assign teacher' }}</h4>
                                            <input type="hidden" name="section_id" value="{{ $section->id }}">
                                            <input type="hidden" name="section_subject_id" value="{{ $offering->id }}">
                                            <input type="hidden" name="assignment_type" value="subject_teacher">
                                            <input type="hidden" name="is_active" value="1">
                                            <input type="hidden" name="replace_existing" value="1">
                                            @if ($startOpen && old('form') !== 'class_time')
                                                @error('staff_profile_id')<p class="text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                                                @error('schedule_text')<p class="text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                                            @endif
                                            <x-ui.field :for="'teacher_'.$offering->id" label="Teacher">
                                                <select id="teacher_{{ $offering->id }}" name="staff_profile_id" required class="jca-input">
                                                    <option value="">Choose teacher</option>
                                                    @foreach ($staffProfiles as $staff)
                                                        <option value="{{ $staff->id }}" @selected($teachers->first()?->staff_profile_id === $staff->id)>{{ $staff->user->name }}{{ $staff->employee_number ? ' · '.$staff->employee_number : '' }}</option>
                                                    @endforeach
                                                </select>
                                            </x-ui.field>
                                            <p class="text-xs text-muted">Teachers who already have another class at this time are refused. {{ $teachers->isNotEmpty() ? 'The current teacher is deactivated and kept in history.' : '' }}</p>
                                            <x-ui.button type="submit">Save teacher</x-ui.button>
                                        </form>
                                    @endif

                                    @if ($teachers->isNotEmpty() && ($canArchive || $canDelete))
                                        <div class="space-y-2 lg:col-span-2">
                                            <h4 class="text-sm font-black text-brand-navy-900">Current teacher assignment</h4>
                                            @foreach ($teachers as $assignment)
                                                <div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-3">
                                                    <span class="mr-auto text-sm font-bold">{{ $assignment->staffProfile->user->name }}</span>
                                                    @if ($canArchive)
                                                        <form method="POST" action="{{ route('academics.admin.teaching-assignments.update', $assignment) }}">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="active_from" value="{{ $assignment->active_from?->format('Y-m-d') }}">
                                                            <input type="hidden" name="active_until" value="{{ today()->format('Y-m-d') }}">
                                                            <input type="hidden" name="is_active" value="0">
                                                            <x-ui.button type="submit" variant="secondary">Remove from this subject</x-ui.button>
                                                        </form>
                                                    @endif
                                                    <x-academics.delete-record class="w-full" :can="$canDelete" label="this teaching assignment"
                                                        :action="route('academics.admin.teaching-assignments.destroy', $assignment)"
                                                        :usage="$assignmentUsage[$assignment->id] ?? []"
                                                        archive-hint="Use “Remove from this subject” instead; it keeps the grade history." />
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($canArchive)
                                        <div class="space-y-2 border-t border-border pt-3 lg:col-span-2">
                                            <form method="POST" action="{{ route('academics.admin.section-subjects.status', $offering) }}" class="flex flex-wrap items-center gap-3">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="archived">
                                                <x-ui.button type="submit" variant="secondary">Archive this subject for {{ $section->name }}</x-ui.button>
                                                <span class="text-xs text-muted">Hides it from this section's schedule. Grades and history are kept; it can be restored.</span>
                                            </form>
                                            <x-academics.delete-record :can="$canDelete" label="this subject from the section"
                                                :action="route('academics.admin.section-subjects.destroy', $offering)"
                                                :usage="$usage" />
                                        </div>
                                    @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($archivedOfferings->isNotEmpty())
            <details class="mt-4 rounded-xl border border-dashed border-border">
                <summary class="cursor-pointer list-none px-4 py-3 text-sm font-bold text-muted [&::-webkit-details-marker]:hidden">Archived subjects ({{ $archivedOfferings->count() }})</summary>
                <ul class="divide-y divide-border border-t border-border">
                    @foreach ($archivedOfferings as $offering)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <span class="mr-auto text-sm"><strong>{{ $offering->subject->name }}</strong> <span class="text-muted">· {{ $offering->schedule_text ?: 'No class time' }}</span></span>
                            @if ($canArchive && $editable)
                                <form method="POST" action="{{ route('academics.admin.section-subjects.status', $offering) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="active">
                                    <x-ui.button type="submit" variant="secondary">Restore</x-ui.button>
                                </form>
                            @endif
                            <x-academics.delete-record class="w-full" :can="$canDelete" label="this subject from the section"
                                :action="route('academics.admin.section-subjects.destroy', $offering)"
                                :usage="$offeringUsage[$offering->id] ?? []"
                                archive-hint="It stays archived so its history is kept." />
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        @if ($editable)
            <details class="mt-4 rounded-xl border border-dashed border-border bg-canvas/40" @if (old('form') === 'add_subject') open @endif>
                <summary class="cursor-pointer list-none px-4 py-3 font-bold text-brand-navy-700 [&::-webkit-details-marker]:hidden">+ Add a subject to {{ $section->name }}</summary>
                <form method="POST" action="{{ route('academics.admin.section-subjects.store') }}" class="space-y-4 border-t border-border p-4">
                    @csrf
                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                    <x-ui.field for="add_subject" label="Subject" hint="Only this section gets the subject. To add it for the whole grade level next year, change the curriculum.">
                        <select id="add_subject" name="subject_id" required class="jca-input">
                            <option value="">Choose subject</option>
                            @foreach ($subjects->whereNotIn('id', $offeredSubjectIds) as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }} ({{ $subject->code }})</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-academics.schedule-fields id-prefix="add_subject" :use-old="old('form') === 'add_subject'" />
                    <input type="hidden" name="form" value="add_subject">
                    <p class="text-xs text-muted">Leave the times blank if the class time isn't confirmed yet.</p>
                    <x-ui.button type="submit">Add subject</x-ui.button>
                </form>
            </details>
        @endif
    </div>

    {{-- ── Section details, archive, delete ── --}}
    <details class="border-t border-border" @if ($errors->hasAny(['name', 'capacity', 'academic_year_id', 'grade_level_id', 'activity_schedule'])) open @endif>
        <summary class="cursor-pointer list-none px-5 py-4 font-bold text-brand-navy-700 hover:bg-canvas sm:px-6 [&::-webkit-details-marker]:hidden">Edit section details</summary>
        <div class="space-y-4 border-t border-border px-5 pb-5 pt-4 sm:px-6">
            <form method="POST" action="{{ route('academics.admin.sections.update', $section) }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf @method('PATCH')
                @if ($structurallyLocked)
                    <input type="hidden" name="academic_year_id" value="{{ $section->academic_year_id }}">
                    <input type="hidden" name="grade_level_id" value="{{ $section->grade_level_id }}">
                    <div class="rounded-lg bg-canvas p-3 text-sm sm:col-span-2 lg:col-span-4">
                        <span class="font-bold">{{ $year->code }} · {{ $section->gradeLevel->name }}</span>
                        <span class="text-muted">— locked because this section {{ $section->curriculum_id ? 'came from the curriculum' : 'already has students or teachers' }}. To move it, add a new section and archive this one.</span>
                    </div>
                @else
                    <x-ui.field for="sec_year" label="School year">
                        <select id="sec_year" name="academic_year_id" class="jca-input">
                            @foreach ($years->whereIn('status', ['planned', 'open']) as $option)
                                <option value="{{ $option->id }}" @selected($section->academic_year_id === $option->id)>{{ $option->code }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field for="sec_grade" label="Grade level">
                        <select id="sec_grade" name="grade_level_id" class="jca-input">
                            @foreach ($grades as $option)
                                <option value="{{ $option->id }}" @selected($section->grade_level_id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                @endif
                <x-ui.field for="sec_name" label="Section name">
                    <input id="sec_name" name="name" value="{{ old('name', $section->name) }}" required maxlength="100" class="jca-input">
                </x-ui.field>
                <x-ui.field for="sec_capacity" label="Capacity">
                    <input id="sec_capacity" name="capacity" type="number" min="1" max="100" value="{{ old('capacity', $section->capacity) }}" required class="jca-input">
                </x-ui.field>
                <x-ui.field for="sec_order" label="Display order">
                    <input id="sec_order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $section->sort_order) }}" class="jca-input">
                </x-ui.field>
                @if ($canArchive)
                    <x-ui.field for="sec_status" label="Status" hint="Archived sections are hidden from placement and this year's lists.">
                        <select id="sec_status" name="status" class="jca-input">
                            <option value="active" @selected($section->status === 'active')>Active</option>
                            <option value="inactive" @selected($section->status === 'inactive')>Archived</option>
                        </select>
                    </x-ui.field>
                @else
                    <input type="hidden" name="status" value="{{ $section->status }}">
                @endif
                <x-ui.field for="sec_activity" label="Activity program / notes (optional)" class="sm:col-span-2 lg:col-span-4">
                    <textarea id="sec_activity" name="activity_schedule" rows="2" maxlength="4000" class="jca-input">{{ old('activity_schedule', $section->activity_schedule) }}</textarea>
                </x-ui.field>
                <div class="sm:col-span-2 lg:col-span-4">
                    <x-ui.button type="submit" variant="secondary">Save section details</x-ui.button>
                </div>
            </form>
            <x-academics.delete-record :can="$canDelete" :label="'section '.$section->name"
                :action="route('academics.admin.sections.destroy', $section)"
                :usage="$sectionUsage"
                archive-hint="Set its status to Archived instead." />
        </div>
    </details>
</section>
