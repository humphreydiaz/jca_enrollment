<x-layouts.app title="Academic catalog">
    <div class="space-y-6">

        {{-- ── Page header ── --}}
        <x-ui.page-header
            title="Academic catalog and teaching assignments"
            description="Manage the reusable school structure. The official JCA catalog has not yet been supplied, so this installation intentionally starts empty. Deactivate rather than delete records that may later be used as history."
            eyebrow="Academics"
        />

        {{-- ── Anchor navigation ── --}}
        <nav class="flex gap-2 overflow-x-auto rounded-xl border border-border bg-white p-2 shadow-sm" aria-label="Catalog sections">
            <a href="#school-years" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-bold text-brand-navy-700 hover:bg-brand-blue-100">School years</a>
            <a href="#grade-levels" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-bold text-brand-navy-700 hover:bg-brand-blue-100">Grade levels</a>
            <a href="#subjects" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-bold text-brand-navy-700 hover:bg-brand-blue-100">Subjects</a>
            <a href="#sections" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-bold text-brand-navy-700 hover:bg-brand-blue-100">Sections</a>
            <a href="#section-details" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-bold text-brand-navy-700 hover:bg-brand-blue-100">Subjects &amp; assignments</a>
        </nav>

        {{-- ── School years & Grade levels ── --}}
        <div class="grid gap-6 xl:grid-cols-2">

            {{-- School years --}}
            <section id="school-years" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="year-title">
                <div class="flex items-center gap-3 border-b border-border px-5 py-4 sm:px-6">
                    <span class="grid size-9 place-items-center rounded-lg bg-brand-blue-100 text-brand-navy-700">
                        <x-ui.icon name="audit" size="size-4" />
                    </span>
                    <div>
                        <h2 id="year-title" class="text-lg font-black text-brand-navy-900">School years</h2>
                        <p class="text-xs text-muted">Define enrollment periods and their status.</p>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    <form method="POST" action="{{ route('academics.admin.academic-years.store') }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <x-ui.field for="code" label="Code">
                            <input id="code" name="code" required value="{{ old('code') }}" placeholder="2026-2027" class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="name" label="Display name">
                            <input id="name" name="name" required value="{{ old('name') }}" placeholder="School Year 2026-2027" class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="starts_on" label="Starts">
                            <input id="starts_on" name="starts_on" required type="date" value="{{ old('starts_on') }}" class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="ends_on" label="Ends">
                            <input id="ends_on" name="ends_on" required type="date" value="{{ old('ends_on') }}" class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="enrollment_opens_on" label="Enrollment opens">
                            <input id="enrollment_opens_on" name="enrollment_opens_on" type="date" value="{{ old('enrollment_opens_on') }}" class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="enrollment_closes_on" label="Enrollment closes">
                            <input id="enrollment_closes_on" name="enrollment_closes_on" type="date" value="{{ old('enrollment_closes_on') }}" class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="status" label="Status">
                            <select id="status" name="status" class="jca-input">
                                @foreach(['planned', 'open', 'closed', 'archived'] as $status)
                                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <div class="sm:col-span-2">
                            <x-ui.button type="submit">Save school year</x-ui.button>
                        </div>
                    </form>

                    {{-- Existing school years --}}
                    <div class="mt-6 space-y-3">
                        @forelse($academicYears as $year)
                            <details class="group rounded-xl border border-border bg-white transition-shadow hover:shadow-sm">
                                <summary class="flex cursor-pointer items-center gap-3 px-4 py-3 font-bold text-ink">
                                    <span class="grid size-8 place-items-center rounded-lg bg-canvas text-muted group-open:bg-brand-blue-100 group-open:text-brand-navy-700">
                                        <x-ui.icon name="audit" size="size-4" />
                                    </span>
                                    <span class="mr-auto">{{ $year->name }}</span>
                                    <x-ui.status-badge :status="$year->status" />
                                </summary>
                                <form method="POST" action="{{ route('academics.admin.academic-years.update', $year) }}" class="border-t border-border px-4 pb-4 pt-4">
                                    @csrf @method('PATCH')
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <x-ui.field for="year_code_{{ $year->id }}" label="Code">
                                            <input id="year_code_{{ $year->id }}" name="code" value="{{ $year->code }}" required class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="year_name_{{ $year->id }}" label="Display name">
                                            <input id="year_name_{{ $year->id }}" name="name" value="{{ $year->name }}" required class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="year_starts_{{ $year->id }}" label="Starts">
                                            <input id="year_starts_{{ $year->id }}" name="starts_on" type="date" value="{{ $year->starts_on->format('Y-m-d') }}" required class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="year_ends_{{ $year->id }}" label="Ends">
                                            <input id="year_ends_{{ $year->id }}" name="ends_on" type="date" value="{{ $year->ends_on->format('Y-m-d') }}" required class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="year_enrollment_opens_{{ $year->id }}" label="Enrollment opens">
                                            <input id="year_enrollment_opens_{{ $year->id }}" name="enrollment_opens_on" type="date" value="{{ $year->enrollment_opens_on?->format('Y-m-d') }}" class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="year_enrollment_closes_{{ $year->id }}" label="Enrollment closes">
                                            <input id="year_enrollment_closes_{{ $year->id }}" name="enrollment_closes_on" type="date" value="{{ $year->enrollment_closes_on?->format('Y-m-d') }}" class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="year_status_{{ $year->id }}" label="Status">
                                            <select id="year_status_{{ $year->id }}" name="status" class="jca-input">
                                                @foreach(['planned', 'open', 'closed', 'archived'] as $status)
                                                    <option value="{{ $status }}" @selected($year->status === $status)>{{ ucfirst($status) }}</option>
                                                @endforeach
                                            </select>
                                        </x-ui.field>
                                    </div>
                                    <div class="mt-4">
                                        <x-ui.button type="submit" variant="secondary">Update / archive</x-ui.button>
                                    </div>
                                </form>
                            </details>
                        @empty
                            <x-ui.empty-state icon="audit" title="No school years yet" description="Add the first school year above to begin building the academic catalog." />
                        @endforelse
                    </div>
                </div>
            </section>

            {{-- Grade levels --}}
            <section id="grade-levels" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="grade-title">
                <div class="flex items-center gap-3 border-b border-border px-5 py-4 sm:px-6">
                    <span class="grid size-9 place-items-center rounded-lg bg-brand-blue-100 text-brand-navy-700">
                        <x-ui.icon name="progress" size="size-4" />
                    </span>
                    <div>
                        <h2 id="grade-title" class="text-lg font-black text-brand-navy-900">Grade levels</h2>
                        <p class="text-xs text-muted">Define the progression of grade levels offered.</p>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    <form method="POST" action="{{ route('academics.admin.grade-levels.store') }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <x-ui.field for="grade_code" label="Code">
                            <input id="grade_code" name="code" required class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="grade_name" label="Name">
                            <input id="grade_name" name="name" required class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="sequence" label="Sequence">
                            <input id="sequence" name="sequence" required min="1" type="number" class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="school_stage" label="Stage">
                            <input id="school_stage" name="school_stage" placeholder="Early years, elementary, etc." class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="next_grade_level_id" label="Next grade (optional)">
                            <select id="next_grade_level_id" name="next_grade_level_id" class="jca-input">
                                <option value="">None yet</option>
                                @foreach($gradeLevels->where('is_active') as $grade)
                                    <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <input type="hidden" name="is_active" value="1" />
                        <div class="sm:col-span-2">
                            <x-ui.button type="submit">Save grade level</x-ui.button>
                        </div>
                    </form>

                    {{-- Existing grade levels --}}
                    <div class="mt-6 space-y-3">
                        @forelse($gradeLevels as $grade)
                            <details class="group rounded-xl border border-border bg-white transition-shadow hover:shadow-sm">
                                <summary class="flex cursor-pointer items-center gap-3 px-4 py-3 font-bold text-ink">
                                    <span class="grid size-8 place-items-center rounded-lg bg-canvas text-muted group-open:bg-brand-blue-100 group-open:text-brand-navy-700">
                                        <x-ui.icon name="progress" size="size-4" />
                                    </span>
                                    <span class="mr-auto">{{ $grade->sequence }}. {{ $grade->name }}</span>
                                    <x-ui.status-badge :status="$grade->is_active ? 'active' : 'inactive'" />
                                </summary>
                                <form method="POST" action="{{ route('academics.admin.grade-levels.update', $grade) }}" class="border-t border-border px-4 pb-4 pt-4">
                                    @csrf @method('PATCH')
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <x-ui.field for="grade_code_{{ $grade->id }}" label="Code">
                                            <input id="grade_code_{{ $grade->id }}" name="code" required value="{{ $grade->code }}" class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="grade_name_{{ $grade->id }}" label="Name">
                                            <input id="grade_name_{{ $grade->id }}" name="name" required value="{{ $grade->name }}" class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="grade_seq_{{ $grade->id }}" label="Sequence">
                                            <input id="grade_seq_{{ $grade->id }}" name="sequence" required type="number" min="1" value="{{ $grade->sequence }}" class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="grade_stage_{{ $grade->id }}" label="Stage">
                                            <input id="grade_stage_{{ $grade->id }}" name="school_stage" value="{{ $grade->school_stage }}" class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="grade_next_{{ $grade->id }}" label="Next grade">
                                            <select id="grade_next_{{ $grade->id }}" name="next_grade_level_id" class="jca-input">
                                                <option value="">None</option>
                                                @foreach($gradeLevels->where('is_active')->where('id', '!=', $grade->id) as $next)
                                                    <option value="{{ $next->id }}" @selected($grade->next_grade_level_id === $next->id)>{{ $next->name }}</option>
                                                @endforeach
                                            </select>
                                        </x-ui.field>
                                    </div>
                                    <div class="mt-4 flex items-center gap-4">
                                        <label class="jca-check">
                                            <input type="hidden" name="is_active" value="0" />
                                            <input name="is_active" value="1" type="checkbox" @checked($grade->is_active) />
                                            Available for new choices
                                        </label>
                                        <x-ui.button type="submit" variant="secondary">Update</x-ui.button>
                                    </div>
                                </form>
                            </details>
                        @empty
                            <x-ui.empty-state icon="progress" title="No grade levels yet" description="Add the first grade level above to define the school's academic progression." />
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        {{-- ── Subjects & Sections ── --}}
        <div class="grid gap-6 xl:grid-cols-2">

            {{-- Subjects --}}
            <section id="subjects" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="subject-title">
                <div class="flex items-center gap-3 border-b border-border px-5 py-4 sm:px-6">
                    <span class="grid size-9 place-items-center rounded-lg bg-brand-blue-100 text-brand-navy-700">
                        <x-ui.icon name="academics" size="size-4" />
                    </span>
                    <div>
                        <h2 id="subject-title" class="text-lg font-black text-brand-navy-900">Subjects</h2>
                        <p class="text-xs text-muted">Catalog of subjects available for section assignment.</p>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    <form method="POST" action="{{ route('academics.admin.subjects.store') }}" class="grid gap-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field for="subject_code" label="Code">
                                <input id="subject_code" name="code" required class="jca-input" />
                            </x-ui.field>
                            <x-ui.field for="subject_name" label="Name">
                                <input id="subject_name" name="name" required class="jca-input" />
                            </x-ui.field>
                        </div>
                        <x-ui.field for="description" label="Description">
                            <textarea id="description" name="description" rows="2" class="jca-input"></textarea>
                        </x-ui.field>
                        <input type="hidden" name="is_active" value="1" />
                        <div>
                            <x-ui.button type="submit">Save subject</x-ui.button>
                        </div>
                    </form>

                    {{-- Existing subjects --}}
                    <div class="mt-6 space-y-3">
                        @forelse($subjects as $subject)
                            <details class="group rounded-xl border border-border bg-white transition-shadow hover:shadow-sm">
                                <summary class="flex cursor-pointer items-center gap-3 px-4 py-3 font-bold text-ink">
                                    <span class="grid size-8 place-items-center rounded-lg bg-canvas text-muted group-open:bg-brand-blue-100 group-open:text-brand-navy-700">
                                        <x-ui.icon name="academics" size="size-4" />
                                    </span>
                                    <span class="mr-auto">{{ $subject->code }} · {{ $subject->name }}</span>
                                    <x-ui.status-badge :status="$subject->is_active ? 'active' : 'inactive'" />
                                </summary>
                                <form method="POST" action="{{ route('academics.admin.subjects.update', $subject) }}" class="border-t border-border px-4 pb-4 pt-4">
                                    @csrf @method('PATCH')
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <x-ui.field for="subject_code_{{ $subject->id }}" label="Code">
                                            <input id="subject_code_{{ $subject->id }}" name="code" required value="{{ $subject->code }}" class="jca-input" />
                                        </x-ui.field>
                                        <x-ui.field for="subject_name_{{ $subject->id }}" label="Name">
                                            <input id="subject_name_{{ $subject->id }}" name="name" required value="{{ $subject->name }}" class="jca-input" />
                                        </x-ui.field>
                                    </div>
                                    <div class="mt-4">
                                        <x-ui.field for="subject_desc_{{ $subject->id }}" label="Description">
                                            <textarea id="subject_desc_{{ $subject->id }}" name="description" rows="2" class="jca-input">{{ $subject->description }}</textarea>
                                        </x-ui.field>
                                    </div>
                                    <div class="mt-4 flex items-center gap-4">
                                        <label class="jca-check">
                                            <input type="hidden" name="is_active" value="0" />
                                            <input name="is_active" value="1" type="checkbox" @checked($subject->is_active) />
                                            Available for new choices
                                        </label>
                                        <x-ui.button type="submit" variant="secondary">Update</x-ui.button>
                                    </div>
                                </form>
                            </details>
                        @empty
                            <x-ui.empty-state icon="academics" title="No subjects yet" description="Add the first subject above. Subjects can then be assigned to sections." />
                        @endforelse
                    </div>
                </div>
            </section>

            {{-- Sections --}}
            <section id="sections" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="section-title">
                <div class="flex items-center gap-3 border-b border-border px-5 py-4 sm:px-6">
                    <span class="grid size-9 place-items-center rounded-lg bg-brand-blue-100 text-brand-navy-700">
                        <x-ui.icon name="sections" size="size-4" />
                    </span>
                    <div>
                        <h2 id="section-title" class="text-lg font-black text-brand-navy-900">Sections</h2>
                        <p class="text-xs text-muted">Create sections within a school year and grade level.</p>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    <form method="POST" action="{{ route('academics.admin.sections.store') }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <x-ui.field for="academic_year_id" label="School year">
                            <select id="academic_year_id" name="academic_year_id" required class="jca-input">
                                <option value="">Select year</option>
                                @foreach($academicYears->whereIn('status', ['planned', 'open']) as $year)
                                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field for="grade_level_id" label="Grade">
                            <select id="grade_level_id" name="grade_level_id" required class="jca-input">
                                <option value="">Select grade</option>
                                @foreach($gradeLevels->where('is_active') as $grade)
                                    <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field for="section_name" label="Section name">
                            <input id="section_name" name="name" required class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="capacity" label="Capacity">
                            <input id="capacity" name="capacity" required type="number" min="1" max="100" value="15" class="jca-input" />
                        </x-ui.field>
                        <x-ui.field for="sort_order" label="Display order">
                            <input id="sort_order" name="sort_order" type="number" min="0" value="0" class="jca-input" />
                        </x-ui.field>
                        <input type="hidden" name="status" value="active" />
                        <div class="sm:col-span-2">
                            <x-ui.button type="submit">Save section</x-ui.button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        {{-- ── Section subjects & teaching assignments ── --}}
        <section id="section-details" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="section-details-title">
            <div class="flex items-center gap-3 border-b border-border px-5 py-4 sm:px-6">
                <span class="grid size-9 place-items-center rounded-lg bg-brand-blue-100 text-brand-navy-700">
                    <x-ui.icon name="teaching" size="size-4" />
                </span>
                <div>
                    <h2 id="section-details-title" class="text-lg font-black text-brand-navy-900">Section subjects and teaching assignments</h2>
                    <p class="text-xs text-muted">Link subjects to sections and assign teachers.</p>
                </div>
            </div>

            <div class="p-5 sm:p-6">
                <div class="grid gap-6 xl:grid-cols-2">

                    {{-- Add subject / schedule form --}}
                    <div class="rounded-xl border border-dashed border-border bg-canvas/50 p-5">
                        <h3 class="flex items-center gap-2 font-bold text-brand-navy-900">
                            <x-ui.icon name="academics" size="size-4" />
                            Add subject / schedule
                        </h3>
                        <form method="POST" action="{{ route('academics.admin.section-subjects.store') }}" class="mt-4 grid gap-4">
                            @csrf
                            <x-ui.field for="subject_section_id" label="Section">
                                <select id="subject_section_id" name="section_id" required class="jca-input">
                                    <option value="">Select section</option>
                                    @foreach($sections->where('status', 'active') as $section)
                                        <option value="{{ $section->id }}">{{ $section->academicYear->code }} · {{ $section->gradeLevel->name }} · {{ $section->name }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <x-ui.field for="subject_id" label="Subject">
                                <select id="subject_id" name="subject_id" required class="jca-input">
                                    <option value="">Select subject</option>
                                    @foreach($subjects->where('is_active') as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <x-ui.field for="schedule_text" label="Schedule note">
                                <textarea id="schedule_text" name="schedule_text" rows="2" class="jca-input" placeholder="Confirm official meeting time with JCA."></textarea>
                            </x-ui.field>
                            <div>
                                <x-ui.button type="submit">Save section subject</x-ui.button>
                            </div>
                        </form>
                    </div>

                    {{-- Add teaching assignment form --}}
                    <div class="rounded-xl border border-dashed border-border bg-canvas/50 p-5">
                        <h3 class="flex items-center gap-2 font-bold text-brand-navy-900">
                            <x-ui.icon name="teaching" size="size-4" />
                            Add teaching assignment
                        </h3>
                        <form method="POST" action="{{ route('academics.admin.teaching-assignments.store') }}" class="mt-4 grid gap-4">
                            @csrf
                            <x-ui.field for="staff_profile_id" label="Teacher">
                                <select id="staff_profile_id" name="staff_profile_id" required class="jca-input">
                                    <option value="">Select teacher</option>
                                    @foreach($staffProfiles as $staff)
                                        <option value="{{ $staff->id }}">{{ $staff->user->name }}{{ $staff->employee_number ? ' · '.$staff->employee_number : '' }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <x-ui.field for="assignment_section_id" label="Section">
                                <select id="assignment_section_id" name="section_id" required class="jca-input">
                                    <option value="">Select section</option>
                                    @foreach($sections->where('status', 'active') as $section)
                                        <option value="{{ $section->id }}">{{ $section->academicYear->code }} · {{ $section->gradeLevel->name }} · {{ $section->name }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <x-ui.field for="assignment_type" label="Assignment type">
                                <select id="assignment_type" name="assignment_type" required class="jca-input">
                                    <option value="adviser">Adviser</option>
                                    <option value="subject_teacher">Subject teacher</option>
                                </select>
                            </x-ui.field>
                            <x-ui.field for="section_subject_id" label="Section subject (subject teachers only)">
                                <select id="section_subject_id" name="section_subject_id" class="jca-input">
                                    <option value="">Select section subject</option>
                                    @foreach($sections as $section)
                                        @foreach($section->sectionSubjects as $sectionSubject)
                                            <option value="{{ $sectionSubject->id }}">{{ $section->name }} · {{ $sectionSubject->subject->name }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-ui.field for="active_from" label="Effective from">
                                    <input id="active_from" name="active_from" type="date" class="jca-input" />
                                </x-ui.field>
                                <x-ui.field for="active_until" label="Effective until">
                                    <input id="active_until" name="active_until" type="date" class="jca-input" />
                                </x-ui.field>
                            </div>
                            <input type="hidden" name="is_active" value="1" />
                            <div>
                                <x-ui.button type="submit">Save teaching assignment</x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Existing sections with subjects & assignments --}}
                <div class="mt-8 space-y-4">
                    @forelse($sections as $section)
                        <details class="group rounded-xl border border-border bg-white transition-shadow hover:shadow-sm">
                            <summary class="flex cursor-pointer flex-wrap items-center gap-3 px-4 py-3 font-bold text-ink">
                                <span class="grid size-8 place-items-center rounded-lg bg-canvas text-muted group-open:bg-brand-blue-100 group-open:text-brand-navy-700">
                                    <x-ui.icon name="sections" size="size-4" />
                                </span>
                                <span class="mr-auto">
                                    {{ $section->academicYear->code }} · {{ $section->gradeLevel->name }} · {{ $section->name }}
                                    <span class="ml-1 text-sm font-semibold text-muted">capacity {{ $section->capacity }}</span>
                                </span>
                                <x-ui.status-badge :status="$section->status" />
                            </summary>

                            <div class="border-t border-border px-4 pb-5 pt-4">
                                <div class="grid gap-6 xl:grid-cols-2">

                                    {{-- Section edit form --}}
                                    <div>
                                        <h4 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">Section details</h4>
                                        <form method="POST" action="{{ route('academics.admin.sections.update', $section) }}" class="grid gap-4 sm:grid-cols-2">
                                            @csrf @method('PATCH')
                                            <x-ui.field for="sec_year_{{ $section->id }}" label="School year">
                                                <select id="sec_year_{{ $section->id }}" name="academic_year_id" class="jca-input">
                                                    @foreach($academicYears as $year)
                                                        <option value="{{ $year->id }}" @selected($section->academic_year_id === $year->id)>{{ $year->code }}</option>
                                                    @endforeach
                                                </select>
                                            </x-ui.field>
                                            <x-ui.field for="sec_grade_{{ $section->id }}" label="Grade">
                                                <select id="sec_grade_{{ $section->id }}" name="grade_level_id" class="jca-input">
                                                    @foreach($gradeLevels as $grade)
                                                        <option value="{{ $grade->id }}" @selected($section->grade_level_id === $grade->id)>{{ $grade->name }}</option>
                                                    @endforeach
                                                </select>
                                            </x-ui.field>
                                            <x-ui.field for="sec_name_{{ $section->id }}" label="Name">
                                                <input id="sec_name_{{ $section->id }}" name="name" value="{{ $section->name }}" required class="jca-input" />
                                            </x-ui.field>
                                            <x-ui.field for="sec_cap_{{ $section->id }}" label="Capacity">
                                                <input id="sec_cap_{{ $section->id }}" name="capacity" type="number" min="1" max="100" value="{{ $section->capacity }}" required class="jca-input" />
                                            </x-ui.field>
                                            <x-ui.field for="sec_order_{{ $section->id }}" label="Display order">
                                                <input id="sec_order_{{ $section->id }}" name="sort_order" type="number" min="0" value="{{ $section->sort_order }}" class="jca-input" />
                                            </x-ui.field>
                                            <x-ui.field for="sec_status_{{ $section->id }}" label="Status">
                                                <select id="sec_status_{{ $section->id }}" name="status" class="jca-input">
                                                    <option value="active" @selected($section->status === 'active')>Active</option>
                                                    <option value="inactive" @selected($section->status === 'inactive')>Inactive</option>
                                                </select>
                                            </x-ui.field>
                                            <div class="sm:col-span-2">
                                                <x-ui.button type="submit" variant="secondary">Update section</x-ui.button>
                                            </div>
                                        </form>
                                    </div>

                                    {{-- Subjects & assignments --}}
                                    <div class="space-y-5">
                                        {{-- Section subjects --}}
                                        <div>
                                            <h4 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">Assigned subjects</h4>
                                            <ul class="space-y-2">
                                                @forelse($section->sectionSubjects as $sectionSubject)
                                                    <li class="rounded-lg bg-canvas p-3">
                                                        <strong class="text-sm text-brand-navy-900">{{ $sectionSubject->subject->name }}</strong>
                                                        <form method="POST" action="{{ route('academics.admin.section-subjects.update', $sectionSubject) }}" class="mt-2 flex items-end gap-2">
                                                            @csrf @method('PATCH')
                                                            <div class="min-w-0 flex-1">
                                                                <label for="sched_{{ $sectionSubject->id }}" class="block text-xs font-semibold text-muted">Schedule</label>
                                                                <input id="sched_{{ $sectionSubject->id }}" name="schedule_text" value="{{ $sectionSubject->schedule_text }}" aria-label="Schedule for {{ $sectionSubject->subject->name }}" class="jca-input" />
                                                            </div>
                                                            <x-ui.button type="submit" variant="secondary">Save</x-ui.button>
                                                        </form>
                                                    </li>
                                                @empty
                                                    <li class="rounded-lg border border-dashed border-border px-4 py-3 text-center text-sm text-muted">No subjects assigned.</li>
                                                @endforelse
                                            </ul>
                                        </div>

                                        {{-- Teaching assignments --}}
                                        <div>
                                            <h4 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">Teaching assignments</h4>
                                            <ul class="space-y-2">
                                                @forelse($section->teachingAssignments as $assignment)
                                                    <li class="rounded-lg bg-canvas p-3">
                                                        <div class="flex items-center gap-2">
                                                            <strong class="text-sm text-brand-navy-900">{{ $assignment->staffProfile->user->name }}</strong>
                                                            <span class="text-xs text-muted">·</span>
                                                            <span class="text-sm text-muted">{{ $assignment->assignment_type === 'adviser' ? 'Adviser' : $assignment->sectionSubject?->subject->name }}</span>
                                                            <x-ui.status-badge :status="$assignment->is_active ? 'active' : 'inactive'" />
                                                        </div>
                                                        <form method="POST" action="{{ route('academics.admin.teaching-assignments.update', $assignment) }}" class="mt-2 flex flex-wrap items-end gap-3">
                                                            @csrf @method('PATCH')
                                                            <div>
                                                                <label for="assign_from_{{ $assignment->id }}" class="block text-xs font-semibold text-muted">From</label>
                                                                <input id="assign_from_{{ $assignment->id }}" name="active_from" type="date" value="{{ $assignment->active_from?->format('Y-m-d') }}" aria-label="Assignment start date" class="jca-input" />
                                                            </div>
                                                            <div>
                                                                <label for="assign_until_{{ $assignment->id }}" class="block text-xs font-semibold text-muted">Until</label>
                                                                <input id="assign_until_{{ $assignment->id }}" name="active_until" type="date" value="{{ $assignment->active_until?->format('Y-m-d') }}" aria-label="Assignment end date" class="jca-input" />
                                                            </div>
                                                            <label class="jca-check">
                                                                <input type="hidden" name="is_active" value="0" />
                                                                <input type="checkbox" name="is_active" value="1" @checked($assignment->is_active) />
                                                                Active
                                                            </label>
                                                            <x-ui.button type="submit" variant="secondary">Update</x-ui.button>
                                                        </form>
                                                    </li>
                                                @empty
                                                    <li class="rounded-lg border border-dashed border-border px-4 py-3 text-center text-sm text-muted">No teachers assigned.</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </details>
                    @empty
                        <x-ui.empty-state icon="sections" title="No sections created yet" description="Create a school year, grade level, then a section before adding schedules or teaching assignments." />
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
