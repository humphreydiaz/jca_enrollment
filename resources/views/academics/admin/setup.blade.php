<x-layouts.app title="Academics · Setup">
    @php
        $user = auth()->user();
        $canArchive = $user->can('archive', \App\Modules\Academics\Models\AcademicYear::class);
        $canDelete = $user->can('delete', \App\Modules\Academics\Models\AcademicYear::class);
        $latest = $years->first();
        $suggestedStart = $latest ? $latest->starts_on->copy()->addYear() : today()->month(6)->startOfMonth();
        $suggestedCode = $suggestedStart->format('Y').'-'.$suggestedStart->copy()->addYear()->format('Y');
        $suggestedEnd = $latest ? $latest->ends_on->copy()->addYear() : $suggestedStart->copy()->addMonths(10);
        $yearQuery = $year ? ['year' => $year->public_id] : [];
        $form = old('form');
    @endphp

    <div class="space-y-6">
        <x-academics.tabs active="setup" :year="$year"
            description="School years, grade levels, and the subject catalog rarely change. Records already used by enrollments, grades, or finance can be archived; only unused records can be deleted." />

        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Setup sections">
            <a href="#school-years" class="rounded-lg border border-border bg-white px-3 py-1.5 font-bold text-brand-navy-700 hover:bg-brand-blue-100">School years</a>
            <a href="#grade-levels" class="rounded-lg border border-border bg-white px-3 py-1.5 font-bold text-brand-navy-700 hover:bg-brand-blue-100">Grade levels</a>
            <a href="#subjects" class="rounded-lg border border-border bg-white px-3 py-1.5 font-bold text-brand-navy-700 hover:bg-brand-blue-100">Subjects</a>
            <a href="#sections" class="rounded-lg border border-border bg-white px-3 py-1.5 font-bold text-brand-navy-700 hover:bg-brand-blue-100">Sections by year</a>
        </nav>

        {{-- ── School years ── --}}
        <section id="school-years" class="jca-card scroll-mt-28 overflow-hidden" aria-labelledby="years-title">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4 sm:px-6">
                <div>
                    <h2 id="years-title" class="text-lg font-black text-brand-navy-900">School years</h2>
                    <p class="text-xs text-muted">Newest first. The current school year opens by default on every Academics screen.</p>
                </div>
            </div>

            <ul class="divide-y divide-border">
                @forelse ($years as $item)
                    @php $usage = $yearUsage[$item->id] ?? []; @endphp
                    <li>
                        <details class="group" @if ($form === 'year_'.$item->id) open @endif>
                            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-canvas sm:px-6 [&::-webkit-details-marker]:hidden">
                                <span class="mr-auto min-w-0">
                                    <span class="font-bold text-ink">{{ $item->name }}</span>
                                    @if ($currentYear?->is($item))<span class="ml-1 rounded bg-brand-blue-100 px-1.5 py-0.5 text-xs font-bold text-brand-navy-700">Current</span>@endif
                                    <span class="block text-xs text-muted">{{ $item->starts_on->format('M j, Y') }} – {{ $item->ends_on->format('M j, Y') }} · {{ in_array($item->id, $publishedYearIds, true) ? 'Class program published' : 'No published class program' }}</span>
                                </span>
                                {{-- JCA_GLOBAL_ENROLLMENT_STATUS --}}
@php
    $todayEnrollment = now('Asia/Manila')->toDateString();

    $enrollmentOpen =
        (bool) $item->enrollment_is_open && $item->status === 'open'
        && (!$item->enrollment_opens_on
            || $todayEnrollment >= $item->enrollment_opens_on->format('Y-m-d'))
        && (!$item->enrollment_closes_on
            || $todayEnrollment <= $item->enrollment_closes_on->format('Y-m-d'));
@endphp

<span class="text-xs font-bold {{ $enrollmentOpen ? 'text-green-700' : 'text-red-700' }}">
    {{ $enrollmentOpen ? 'Enrollment OPEN' : 'Enrollment CLOSED' }}
</span>

                                
<span class="text-sm font-bold text-brand-navy-700 group-open:hidden">Edit</span>
                                <span class="hidden text-sm font-bold text-brand-navy-700 group-open:inline">Close</span>
                            </summary>
                            <div class="space-y-4 border-t border-border bg-canvas/40 px-5 pb-5 pt-4 sm:px-6">
                                <form method="POST" action="{{ route('academics.admin.academic-years.update', $item) }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="form" value="year_{{ $item->id }}">
                                    <x-ui.field :for="'year_code_'.$item->id" label="Code"><input id="year_code_{{ $item->id }}" name="code" value="{{ $item->code }}" required maxlength="30" class="jca-input"></x-ui.field>
                                    <x-ui.field :for="'year_name_'.$item->id" label="Display name"><input id="year_name_{{ $item->id }}" name="name" value="{{ $item->name }}" required class="jca-input"></x-ui.field>
                                    <x-ui.field :for="'year_starts_'.$item->id" label="Starts"><input id="year_starts_{{ $item->id }}" name="starts_on" type="date" value="{{ $item->starts_on->format('Y-m-d') }}" required class="jca-input"></x-ui.field>
                                    <x-ui.field :for="'year_ends_'.$item->id" label="Ends"><input id="year_ends_{{ $item->id }}" name="ends_on" type="date" value="{{ $item->ends_on->format('Y-m-d') }}" required class="jca-input"></x-ui.field>
                                    <x-ui.field :for="'year_opens_'.$item->id" label="Enrollment opens"><input id="year_opens_{{ $item->id }}" name="enrollment_opens_on" type="date" value="{{ $item->enrollment_opens_on?->format('Y-m-d') }}" class="jca-input"></x-ui.field>
                                    <x-ui.field :for="'year_closes_'.$item->id" label="Enrollment closes"><input id="year_closes_{{ $item->id }}" name="enrollment_closes_on" type="date" value="{{ $item->enrollment_closes_on?->format('Y-m-d') }}" class="jca-input"></x-ui.field>
                                    <x-ui.field :for="'year_status_'.$item->id" label="Status" :hint="$canArchive ? null : 'Only the Admin can archive or restore a school year.'">
                                        <select id="year_status_{{ $item->id }}" name="status" class="jca-input">
                                            @foreach (['planned', 'open', 'closed', 'archived'] as $status)
                                                @continue(! $canArchive && $status === 'archived' && $item->status !== 'archived')
                                                <option value="{{ $status }}" @selected($item->status === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                    </x-ui.field>
                                    <div class="flex items-end gap-3">
                                        <x-ui.button type="submit" variant="secondary">Save school year</x-ui.button>
                                    </div>
                                </form>
                                {{-- Staff enrollment controls --}}
                                <div class="flex flex-wrap items-center gap-3 border-t border-border pt-4">
                                    @if ($item->enrollment_is_open)
                                        <form method="POST" action="{{ route('academics.admin.academic-years.enrollment-status', $item) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="enrollment_is_open" value="0">
                                            <button type="submit"
                                                class="inline-flex items-center justify-center rounded-lg border border-red-600 bg-red-600 px-4 py-2 text-sm font-bold !text-white shadow-sm hover:bg-red-700">
                                                Close Enrollment
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('academics.admin.academic-years.enrollment-status', $item) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="enrollment_is_open" value="1">
                                            <button type="submit"
                                                class="inline-flex items-center justify-center rounded-lg border border-green-700 bg-green-700 px-4 py-2 text-sm font-bold !text-white shadow-sm hover:bg-green-800">
                                                Open Enrollment
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <a href="{{ route('academics.admin.index', ['year' => $item->public_id]) }}" class="text-sm font-bold text-brand-navy-700 hover:underline">Open class schedules</a>
                                    <a href="{{ route('academics.admin.curricula.index', ['year' => $item->public_id]) }}" class="text-sm font-bold text-brand-navy-700 hover:underline">Open curriculum</a>
                                </div>
                                <x-academics.delete-record :can="$canDelete" :label="'school year '.$item->code"
                                    :action="route('academics.admin.academic-years.destroy', $item)" :usage="$usage"
                                    archive-hint="Set its status to Archived instead." />
                            </div>
                        </details>
                    </li>
                @empty
                    <li class="p-5 sm:p-6">
                        <x-ui.empty-state icon="audit" title="No school years yet" description="Create the first school year below, then load JCA's class program from the Curriculum tab." />
                    </li>
                @endforelse
            </ul>

            @can('create', \App\Modules\Academics\Models\AcademicYear::class)
                <details class="border-t border-border" @if ($form === 'new_year' || $years->isEmpty()) open @endif>
                    <summary class="cursor-pointer list-none px-5 py-4 font-bold text-brand-navy-700 hover:bg-canvas sm:px-6 [&::-webkit-details-marker]:hidden">+ New school year</summary>
                    <form method="POST" action="{{ route('academics.admin.academic-years.store') }}" class="grid gap-4 border-t border-border px-5 pb-5 pt-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                        @csrf
                        <input type="hidden" name="form" value="new_year">
                        <x-ui.field for="code" label="Code"><input id="code" name="code" required maxlength="30" value="{{ old('code', $suggestedCode) }}" class="jca-input"></x-ui.field>
                        <x-ui.field for="name" label="Display name"><input id="name" name="name" required value="{{ old('name', 'School Year '.$suggestedCode) }}" class="jca-input"></x-ui.field>
                        <x-ui.field for="starts_on" label="Starts"><input id="starts_on" name="starts_on" type="date" required value="{{ old('starts_on', $suggestedStart->format('Y-m-d')) }}" class="jca-input"></x-ui.field>
                        <x-ui.field for="ends_on" label="Ends"><input id="ends_on" name="ends_on" type="date" required value="{{ old('ends_on', $suggestedEnd->format('Y-m-d')) }}" class="jca-input"></x-ui.field>
                        <x-ui.field for="enrollment_opens_on" label="Enrollment opens (optional)"><input id="enrollment_opens_on" name="enrollment_opens_on" type="date" value="{{ old('enrollment_opens_on') }}" class="jca-input"></x-ui.field>
                        <x-ui.field for="enrollment_closes_on" label="Enrollment closes (optional)"><input id="enrollment_closes_on" name="enrollment_closes_on" type="date" value="{{ old('enrollment_closes_on') }}" class="jca-input"></x-ui.field>
                        <x-ui.field for="status" label="Status">
                            <select id="status" name="status" class="jca-input">
                                @foreach (['planned', 'open', 'closed'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'planned') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <div class="flex items-end"><x-ui.button type="submit">Save school year</x-ui.button></div>
                    </form>
                </details>
            @endcan
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            {{-- ── Grade levels ── --}}
            <section id="grade-levels" class="jca-card scroll-mt-28 overflow-hidden" aria-labelledby="grades-title">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h2 id="grades-title" class="text-lg font-black text-brand-navy-900">Grade levels</h2>
                    <p class="text-xs text-muted">Nursery to Grade 10. The order and “next grade” drive promotion, so change them carefully.</p>
                </div>
                <ul class="divide-y divide-border">
                    @forelse ($grades as $item)
                        <li>
                            <details class="group" @if ($form === 'grade_'.$item->id) open @endif>
                                <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-3 hover:bg-canvas sm:px-6 [&::-webkit-details-marker]:hidden">
                                    <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-canvas text-xs font-black text-muted">{{ $item->sequence }}</span>
                                    <span class="mr-auto min-w-0">
                                        <span class="font-bold">{{ $item->name }}</span>
                                        <span class="block text-xs text-muted">{{ $item->school_stage ?: 'No stage' }}{{ $item->nextGradeLevel ? ' · next: '.$item->nextGradeLevel->name : '' }}</span>
                                    </span>
                                    <x-ui.status-badge :status="$item->is_active ? 'active' : 'inactive'" />
                                </summary>
                                <div class="space-y-4 border-t border-border bg-canvas/40 px-5 pb-5 pt-4 sm:px-6">
                                    <form method="POST" action="{{ route('academics.admin.grade-levels.update', $item) }}" class="grid gap-4 sm:grid-cols-2">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="form" value="grade_{{ $item->id }}">
                                        <x-ui.field :for="'grade_code_'.$item->id" label="Code"><input id="grade_code_{{ $item->id }}" name="code" required value="{{ $item->code }}" class="jca-input"></x-ui.field>
                                        <x-ui.field :for="'grade_name_'.$item->id" label="Name"><input id="grade_name_{{ $item->id }}" name="name" required value="{{ $item->name }}" class="jca-input"></x-ui.field>
                                        <x-ui.field :for="'grade_seq_'.$item->id" label="Order"><input id="grade_seq_{{ $item->id }}" name="sequence" required type="number" min="1" value="{{ $item->sequence }}" class="jca-input"></x-ui.field>
                                        <x-ui.field :for="'grade_stage_'.$item->id" label="Stage"><input id="grade_stage_{{ $item->id }}" name="school_stage" value="{{ $item->school_stage }}" class="jca-input"></x-ui.field>
                                        <x-ui.field :for="'grade_next_'.$item->id" label="Next grade" class="sm:col-span-2">
                                            <select id="grade_next_{{ $item->id }}" name="next_grade_level_id" class="jca-input">
                                                <option value="">None (last grade offered)</option>
                                                @foreach ($grades->where('is_active')->where('id', '!=', $item->id) as $next)
                                                    <option value="{{ $next->id }}" @selected($item->next_grade_level_id === $next->id)>{{ $next->name }}</option>
                                                @endforeach
                                            </select>
                                        </x-ui.field>
                                        <div class="flex flex-wrap items-center gap-4 sm:col-span-2">
                                            @if ($canArchive)
                                                <label class="jca-check">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input name="is_active" value="1" type="checkbox" @checked($item->is_active)>
                                                    Available for new sections and enrollments
                                                </label>
                                            @else
                                                <input type="hidden" name="is_active" value="{{ $item->is_active ? 1 : 0 }}">
                                            @endif
                                            <x-ui.button type="submit" variant="secondary">Save grade level</x-ui.button>
                                        </div>
                                    </form>
                                    <x-academics.delete-record :can="$canDelete" :label="$item->name"
                                        :action="route('academics.admin.grade-levels.destroy', $item)" :usage="$gradeUsage[$item->id] ?? []"
                                        archive-hint="Untick “Available” to archive it instead." />
                                </div>
                            </details>
                        </li>
                    @empty
                        <li class="p-5 sm:p-6"><x-ui.empty-state icon="progress" title="No grade levels yet" description="Add the grade levels the school offers." /></li>
                    @endforelse
                </ul>
                @can('create', \App\Modules\Academics\Models\GradeLevel::class)
                    <details class="border-t border-border" @if ($form === 'new_grade') open @endif>
                        <summary class="cursor-pointer list-none px-5 py-4 font-bold text-brand-navy-700 hover:bg-canvas sm:px-6 [&::-webkit-details-marker]:hidden">+ New grade level</summary>
                        <form method="POST" action="{{ route('academics.admin.grade-levels.store') }}" class="grid gap-4 border-t border-border px-5 pb-5 pt-4 sm:grid-cols-2 sm:px-6">
                            @csrf
                            <input type="hidden" name="form" value="new_grade">
                            <input type="hidden" name="is_active" value="1">
                            <x-ui.field for="grade_code" label="Code"><input id="grade_code" name="code" required value="{{ $form === 'new_grade' ? old('code') : '' }}" class="jca-input"></x-ui.field>
                            <x-ui.field for="grade_name" label="Name"><input id="grade_name" name="name" required value="{{ $form === 'new_grade' ? old('name') : '' }}" class="jca-input"></x-ui.field>
                            <x-ui.field for="sequence" label="Order"><input id="sequence" name="sequence" required min="1" type="number" value="{{ $form === 'new_grade' ? old('sequence') : ($grades->max('sequence') + 1) }}" class="jca-input"></x-ui.field>
                            <x-ui.field for="school_stage" label="Stage"><input id="school_stage" name="school_stage" value="{{ $form === 'new_grade' ? old('school_stage') : '' }}" placeholder="Elementary" class="jca-input"></x-ui.field>
                            <x-ui.field for="next_grade_level_id" label="Next grade (optional)" class="sm:col-span-2">
                                <select id="next_grade_level_id" name="next_grade_level_id" class="jca-input">
                                    <option value="">None yet</option>
                                    @foreach ($grades->where('is_active') as $next)<option value="{{ $next->id }}">{{ $next->name }}</option>@endforeach
                                </select>
                            </x-ui.field>
                            <div class="sm:col-span-2"><x-ui.button type="submit">Save grade level</x-ui.button></div>
                        </form>
                    </details>
                @endcan
            </section>

            {{-- ── Subject catalog ── --}}
            <section id="subjects" class="jca-card scroll-mt-28 overflow-hidden" aria-labelledby="subjects-title">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h2 id="subjects-title" class="text-lg font-black text-brand-navy-900">Subject catalog</h2>
                    <p class="text-xs text-muted">Subjects that curricula and sections can use. A subject used by a published curriculum keeps its name; add a new subject instead.</p>
                </div>
                <ul class="divide-y divide-border">
                    @forelse ($subjects as $item)
                        <li>
                            <details class="group" @if ($form === 'subject_'.$item->id) open @endif>
                                <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-3 hover:bg-canvas sm:px-6 [&::-webkit-details-marker]:hidden">
                                    <span class="w-16 shrink-0 text-xs font-black text-muted">{{ $item->code }}</span>
                                    <span class="mr-auto min-w-0 font-bold">{{ $item->name }}</span>
                                    <x-ui.status-badge :status="$item->is_active ? 'active' : 'inactive'" />
                                </summary>
                                <div class="space-y-4 border-t border-border bg-canvas/40 px-5 pb-5 pt-4 sm:px-6">
                                    <form method="POST" action="{{ route('academics.admin.subjects.update', $item) }}" class="grid gap-4 sm:grid-cols-2">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="form" value="subject_{{ $item->id }}">
                                        <x-ui.field :for="'subject_code_'.$item->id" label="Code"><input id="subject_code_{{ $item->id }}" name="code" required value="{{ $item->code }}" class="jca-input"></x-ui.field>
                                        <x-ui.field :for="'subject_name_'.$item->id" label="Name"><input id="subject_name_{{ $item->id }}" name="name" required value="{{ $item->name }}" class="jca-input"></x-ui.field>
                                        <div class="flex flex-wrap items-center gap-4 sm:col-span-2">
                                            @if ($canArchive)
                                                <label class="jca-check">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input name="is_active" value="1" type="checkbox" @checked($item->is_active)>
                                                    Available for new choices
                                                </label>
                                            @else
                                                <input type="hidden" name="is_active" value="{{ $item->is_active ? 1 : 0 }}">
                                            @endif
                                            <x-ui.button type="submit" variant="secondary">Save subject</x-ui.button>
                                        </div>
                                    </form>
                                    <x-academics.delete-record :can="$canDelete" :label="'subject '.$item->code"
                                        :action="route('academics.admin.subjects.destroy', $item)" :usage="$subjectUsage[$item->id] ?? []"
                                        archive-hint="Untick “Available” to archive it instead." />
                                </div>
                            </details>
                        </li>
                    @empty
                        <li class="p-5 sm:p-6"><x-ui.empty-state icon="academics" title="No subjects yet" description="Loading JCA's class program adds its subjects automatically, or add one below." /></li>
                    @endforelse
                </ul>
                @can('create', \App\Modules\Academics\Models\Subject::class)
                    <details class="border-t border-border" @if ($form === 'new_subject') open @endif>
                        <summary class="cursor-pointer list-none px-5 py-4 font-bold text-brand-navy-700 hover:bg-canvas sm:px-6 [&::-webkit-details-marker]:hidden">+ New subject</summary>
                        <form method="POST" action="{{ route('academics.admin.subjects.store') }}" class="grid gap-4 border-t border-border px-5 pb-5 pt-4 sm:grid-cols-[8rem_minmax(0,1fr)_auto] sm:items-end sm:px-6">
                            @csrf
                            <input type="hidden" name="form" value="new_subject">
                            <input type="hidden" name="is_active" value="1">
                            <x-ui.field for="subject_code" label="Code"><input id="subject_code" name="code" required maxlength="30" value="{{ $form === 'new_subject' ? old('code') : '' }}" class="jca-input"></x-ui.field>
                            <x-ui.field for="subject_name" label="Name"><input id="subject_name" name="name" required value="{{ $form === 'new_subject' ? old('name') : '' }}" class="jca-input"></x-ui.field>
                            <x-ui.button type="submit">Save subject</x-ui.button>
                        </form>
                    </details>
                @endcan
            </section>
        </div>

        {{-- ── Sections by year ── --}}
        <section id="sections" class="jca-card scroll-mt-28 overflow-hidden" aria-labelledby="setup-sections-title">
            <div class="border-b border-border px-5 py-4 sm:px-6">
                <h2 id="setup-sections-title" class="text-lg font-black text-brand-navy-900">Sections by year</h2>
                <p class="text-xs text-muted">Every section of one school year. Open a section to manage its subjects, class times, and teachers.</p>
            </div>
            @if ($years->isNotEmpty())
                <div class="border-b border-border p-4">
                    <x-academics.year-filter class="!border-0 !p-0 !shadow-none" :action="route('academics.admin.setup')" hash="sections"
                        :years="$years" :year="$year" :current-year="$currentYear" :show-archived="$showArchived" />
                </div>
            @endif
            @if ($sections->isEmpty())
                <div class="p-5 sm:p-6"><x-ui.empty-state icon="sections" title="No sections for this school year" description="Publish the year's class program, or add sections from the Class schedules tab." /></div>
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead class="bg-canvas text-xs font-extrabold uppercase tracking-wider text-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3 sm:px-6">Grade level</th>
                                <th scope="col" class="px-3 py-3">Section</th>
                                <th scope="col" class="px-3 py-3">Subjects</th>
                                <th scope="col" class="px-3 py-3">Enrolled</th>
                                <th scope="col" class="px-3 py-3">Source</th>
                                <th scope="col" class="px-3 py-3">Status</th>
                                <th scope="col" class="px-5 py-3 sm:px-6"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($sections as $item)
                                <tr class="hover:bg-canvas/70">
                                    <td class="px-5 py-3 sm:px-6">{{ $item->gradeLevel->name }}</td>
                                    <th scope="row" class="px-3 py-3 font-bold text-brand-navy-900">{{ $item->name }}</th>
                                    <td class="px-3 py-3">{{ $item->offerings_count }}</td>
                                    <td class="px-3 py-3">{{ $item->enrolled_count }} / {{ $item->capacity }}</td>
                                    <td class="px-3 py-3 text-muted">{{ $item->curriculum_id ? 'Curriculum' : 'Added manually' }}</td>
                                    <td class="px-3 py-3"><x-ui.status-badge :status="$item->status === 'active' ? 'active' : 'archived'" /></td>
                                    <td class="px-5 py-3 text-right sm:px-6">
                                        <a href="{{ route('academics.admin.index', [...$yearQuery, 'section' => $item->public_id]) }}" class="font-bold text-brand-navy-700 hover:underline">Open<span class="sr-only"> {{ $item->gradeLevel->name }} {{ $item->name }}</span></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
