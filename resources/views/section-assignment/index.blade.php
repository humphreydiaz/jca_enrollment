@php
    $placementRecords = $approvedEnrollments->getCollection()->map(function ($enrollment) use ($recommendations, $sections) {
        $recommendation = $recommendations->get($enrollment->id);
        $eligibleSections = $sections
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->where('grade_level_id', $enrollment->grade_level_id)
            ->values();

        return [
            'publicId' => $enrollment->public_id,
            'name' => $enrollment->student->full_name,
            'reference' => $enrollment->application_number,
            'context' => $enrollment->gradeLevel->name.' · '.$enrollment->academicYear->name,
            'action' => route('section-assignment.confirm', $enrollment),
            'supportReview' => $enrollment->student_category === 'support_review',
            'recommendedSection' => $recommendation?->section?->public_id,
            'recommendation' => $recommendation?->section
                ? 'Recommended: '.$recommendation->section->name.'. Lowest confirmed count, then configured order.'
                : ($recommendation?->reason ?? 'No recommendation is currently available.'),
            'sections' => $eligibleSections->map(fn ($section) => [
                'value' => $section->public_id,
                'label' => $section->name.' · '.$section->confirmed_count.'/'.$section->capacity.' assigned',
            ])->all(),
        ];
    })->values();

    $assignmentRecords = $currentAssignments->getCollection()->map(function ($assignment) use ($sections) {
        return [
            'publicId' => $assignment->public_id,
            'name' => $assignment->enrollment->student->full_name,
            'reference' => $assignment->enrollment->application_number,
            'context' => $assignment->enrollment->gradeLevel->name.' · '.$assignment->enrollment->academicYear->name,
            'currentSection' => $assignment->section->name,
            'capacityOverride' => (bool) $assignment->is_capacity_override,
            'capacityOverrideReason' => $assignment->capacity_override_reason,
            'action' => route('section-assignment.transfer', $assignment),
            'supportReview' => $assignment->enrollment->student_category === 'support_review',
            'sections' => $sections
                ->where('academic_year_id', $assignment->enrollment->academic_year_id)
                ->where('grade_level_id', $assignment->enrollment->grade_level_id)
                ->where('id', '!=', $assignment->section_id)
                ->values()
                ->map(fn ($section) => [
                    'value' => $section->public_id,
                    'label' => $section->name.' · '.$section->confirmed_count.'/'.$section->capacity.' assigned',
                ])->all(),
        ];
    })->values();

    $promotionRecords = $enrolledForPromotion->getCollection()->map(function ($enrollment) use ($futureYears, $promotionGrades) {
        $recommendation = $enrollment->promotionRecommendation;
        $readiness = $promotionGrades[$enrollment->id] ?? null;
        $gradesReady = $readiness?->isReady() ?? false;

        return [
            'publicId' => $enrollment->public_id,
            'name' => $enrollment->student->full_name,
            'reference' => $enrollment->application_number,
            'context' => $enrollment->gradeLevel->name.' · '.$enrollment->academicYear->name,
            'gradeName' => $enrollment->gradeLevel->name,
            'recommendAction' => route('promotion.recommend', $enrollment),
            'createAction' => $recommendation?->eligibility_status === 'eligible' && ! $recommendation->created_enrollment_id && $gradesReady
                ? route('promotion.create-draft', $recommendation)
                : null,
            'staleEligible' => $recommendation?->eligibility_status === 'eligible' && ! $recommendation->created_enrollment_id && ! $gradesReady,
            'grades' => $readiness?->toArray(),
            'status' => $recommendation?->eligibility_status ?? 'unreviewed',
            'statusLabel' => match ($recommendation?->eligibility_status) {
                'eligible' => 'eligible',
                'grades_pending' => 'waiting for approved final grades',
                'ineligible' => 'not eligible',
                default => 'not reviewed',
            },
            'reason' => $recommendation?->reason,
            'target' => collect([$recommendation?->targetGradeLevel?->name, $recommendation?->targetAcademicYear?->name])->filter()->join(' · '),
            'draftCreated' => (bool) $recommendation?->created_enrollment_id,
            'years' => $futureYears
                ->filter(fn ($year) => $year->starts_on->isAfter($enrollment->academicYear->ends_on))
                ->values()
                ->map(fn ($year) => ['value' => $year->public_id, 'label' => $year->name])
                ->all(),
        ];
    })->values();

    $restoredModal = [
        'context' => old('modal_context'),
        'publicId' => old('record_public_id'),
        'section' => old('section_public_id'),
        'reason' => old('reason'),
        'targetYear' => old('target_academic_year_public_id'),
        'supportConfirmed' => old('support_suitability_confirmed') === '1',
        'capacityOverride' => old('override_capacity') === '1',
        'overrideReason' => old('override_reason'),
    ];
    $hasFilters = collect(['search', 'year', 'grade', 'section', 'promotion_status'])->contains(fn ($key) => filled($filters[$key] ?? null));

    $tabs = [
        ['id' => 'awaiting-placement', 'label' => 'Awaiting placement', 'count' => $approvedEnrollments->total(), 'icon' => 'inbox'],
        ['id' => 'current-assignments', 'label' => 'Current assignments', 'count' => $currentAssignments->total(), 'icon' => 'sections'],
        ['id' => 'transfer-history', 'label' => 'Transfer history', 'count' => $assignmentHistory->total(), 'icon' => 'audit'],
        ['id' => 'promotion-review', 'label' => 'Promotion review', 'count' => $enrolledForPromotion->total(), 'icon' => 'progress'],
    ];
@endphp

<x-layouts.app title="Section assignment">
    <div
        class="space-y-6"
        x-data="sectionAssignmentWorkspace(@js($placementRecords), @js($assignmentRecords), @js($promotionRecords), @js($restoredModal))"
    >
        <x-ui.page-header
            title="Section assignment and promotion"
            description="Search and filter large student queues, then complete one focused assignment, transfer, or promotion action at a time."
            eyebrow="Registrar workspace"
        />

        {{-- Summary cards --}}
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Section assignment summary">
            @foreach ($tabs as $tab)
                <button
                    type="button"
                    @click="switchTab('{{ $tab['id'] }}')"
                    :class="activeTab === '{{ $tab['id'] }}' ? 'border-brand-navy-700 bg-brand-blue-100/40' : 'border-border'"
                    class="jca-card group relative flex items-center gap-4 overflow-hidden p-4 text-left transition-all hover:border-brand-navy-700 hover:shadow-md"
                >
                    <span class="absolute inset-x-0 top-0 h-1 rounded-b-full transition-colors" :class="activeTab === '{{ $tab['id'] }}' ? 'bg-brand-navy-700' : 'bg-transparent group-hover:bg-brand-gold-500'"></span>
                    <span class="grid size-11 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition-transform group-hover:scale-110"><x-ui.icon :name="$tab['icon']" /></span>
                    <span>
                        <span class="block text-2xl font-black tabular-nums text-brand-navy-900">{{ number_format($tab['count']) }}</span>
                        <span class="text-sm font-semibold text-muted">{{ $tab['label'] }}</span>
                    </span>
                </button>
            @endforeach
        </section>

        {{-- Filters --}}
        <section class="jca-card p-5 sm:p-6" aria-labelledby="assignment-filter-title">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="assignment-filter-title" class="text-xl font-black text-brand-navy-900">Find students and assignments</h2>
                    <p class="mt-1 text-sm text-muted">Filters apply to every table below; promotion status affects only the promotion table.</p>
                </div>
                @if($hasFilters)
                    <a href="{{ route('section-assignment.index') }}" class="jca-button-secondary">Clear filters</a>
                @endif
            </div>
            <form method="GET" action="{{ route('section-assignment.index') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(5,minmax(0,1fr))_auto]">
                <label class="jca-label">Search
                    <input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" class="jca-input" placeholder="Student, number, application, or section">
                </label>
                <label class="jca-label">School year
                    <select name="year" class="jca-input"><option value="">All years</option>@foreach($filterYears as $year)<option value="{{ $year->public_id }}" @selected(($filters['year'] ?? null) === $year->public_id)>{{ $year->name }}</option>@endforeach</select>
                </label>
                <label class="jca-label">Grade level
                    <select name="grade" class="jca-input"><option value="">All grades</option>@foreach($filterGrades as $grade)<option value="{{ $grade->public_id }}" @selected(($filters['grade'] ?? null) === $grade->public_id)>{{ $grade->name }}</option>@endforeach</select>
                </label>
                <label class="jca-label">Section
                    <select name="section" class="jca-input"><option value="">All sections</option>@foreach($sections as $section)<option value="{{ $section->public_id }}" @selected(($filters['section'] ?? null) === $section->public_id)>{{ $section->gradeLevel->name }} · {{ $section->name }}</option>@endforeach</select>
                </label>
                <label class="jca-label">Promotion status
                    <select name="promotion_status" class="jca-input">
                        <option value="">All statuses</option>
                        @foreach(['unreviewed' => 'Not reviewed', 'eligible' => 'Eligible', 'grades_pending' => 'Waiting for final grades', 'ineligible' => 'Ineligible', 'draft_created' => 'Draft created'] as $value => $label)<option value="{{ $value }}" @selected(($filters['promotion_status'] ?? null) === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="jca-label">Rows
                    <select name="per_page" class="jca-input">@foreach([15,25,50] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 15) == $size)>{{ $size }}</option>@endforeach</select>
                </label>
                <div class="flex items-end"><x-ui.button type="submit" class="w-full justify-center"><x-ui.icon name="search" /> Apply</x-ui.button></div>
            </form>

            {{-- Grade-level quick-filter pills --}}
            @if($filterGrades->isNotEmpty())
                <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Quick grade filters">
                    <span class="text-xs font-extrabold uppercase tracking-wide text-muted">Grade</span>
                    @foreach($filterGrades as $grade)
                        @php($isActiveGrade = ($filters['grade'] ?? null) === $grade->public_id)
                        <a
                            href="{{ route('section-assignment.index', array_merge(request()->except(['grade', 'awaiting_page', 'assignments_page', 'history_page', 'promotion_page']), ['grade' => $grade->public_id])) }}"
                            @class([
                                'rounded-full px-3 py-1.5 text-xs font-bold transition-colors',
                                'bg-brand-navy-700 text-white' => $isActiveGrade,
                                'bg-slate-100 text-brand-navy-700 hover:bg-brand-blue-100' => ! $isActiveGrade,
                            ])
                        >{{ $grade->name }}</a>
                    @endforeach
                    @if(isset($filters['grade']))
                        <a href="{{ route('section-assignment.index', request()->except(['grade', 'awaiting_page', 'assignments_page', 'history_page', 'promotion_page'])) }}" class="rounded-full bg-red-50 px-3 py-1.5 text-xs font-bold text-brand-red-600 transition-colors hover:bg-red-100">Clear grade ×</a>
                    @endif
                </div>
            @endif

            @if($hasFilters)
                <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Active filters">
                    <span class="text-xs font-extrabold uppercase tracking-wide text-muted">Active filters</span>
                    @foreach([
                        'search' => isset($filters['search']) ? 'Search: '.$filters['search'] : null,
                        'year' => isset($filters['year']) ? 'Year: '.$filterYears->firstWhere('public_id', $filters['year'])?->name : null,
                        'grade' => isset($filters['grade']) ? 'Grade: '.$filterGrades->firstWhere('public_id', $filters['grade'])?->name : null,
                        'section' => isset($filters['section']) ? 'Section: '.$sections->firstWhere('public_id', $filters['section'])?->name : null,
                        'promotion_status' => isset($filters['promotion_status']) ? 'Promotion: '.str($filters['promotion_status'])->replace('_', ' ')->headline() : null,
                    ] as $key => $label)
                        @if($label)
                            <a href="{{ route('section-assignment.index', request()->except([$key, 'awaiting_page', 'assignments_page', 'history_page', 'promotion_page'])) }}" class="rounded-full bg-brand-blue-100 px-3 py-1.5 text-xs font-bold text-brand-navy-700" aria-label="Remove {{ $label }} filter">{{ $label }} <span aria-hidden="true">×</span></a>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Tab navigation --}}
        <nav class="flex gap-1 overflow-x-auto rounded-xl border border-border bg-white p-1.5 shadow-sm" aria-label="Workspace sections">
            @foreach($tabs as $tab)
                <button
                    type="button"
                    @click="switchTab('{{ $tab['id'] }}')"
                    :class="activeTab === '{{ $tab['id'] }}'
                        ? 'bg-brand-navy-700 text-white shadow-sm'
                        : 'text-brand-navy-700 hover:bg-brand-blue-100'"
                    class="flex items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2.5 text-sm font-bold transition-all"
                >
                    <x-ui.icon :name="$tab['icon']" size="size-4" />
                    {{ $tab['label'] }}
                    <span
                        :class="activeTab === '{{ $tab['id'] }}'
                            ? 'bg-white/20 text-white'
                            : 'bg-brand-blue-100 text-brand-navy-700'"
                        class="ml-1 inline-flex min-w-[1.5rem] items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-black tabular-nums"
                    >{{ number_format($tab['count']) }}</span>
                </button>
            @endforeach
        </nav>

        {{-- ==================== Awaiting Placement ==================== --}}
        <section
            id="awaiting-placement"
            x-show="activeTab === 'awaiting-placement'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="jca-card overflow-hidden"
            aria-labelledby="recommendation-title"
        >
            <div class="flex flex-col gap-2 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div><h2 id="recommendation-title" class="text-xl font-black text-brand-navy-900">Approved enrollments awaiting placement</h2><p class="mt-1 text-sm text-muted">Recommendations use current capacity and configured section order.</p></div>
                <p class="text-sm font-bold text-muted">{{ number_format($approvedEnrollments->total()) }} result{{ $approvedEnrollments->total() === 1 ? '' : 's' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Student</th><th class="px-5 py-3">Grade and year</th><th class="px-5 py-3">Recommendation</th><th class="px-5 py-3">Approved</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-border bg-white">
                        @forelse($approvedEnrollments as $enrollment)
                            @php($recommendation = $recommendations->get($enrollment->id))
                            <tr class="transition-colors hover:bg-slate-50/60">
                                <td class="px-5 py-4"><p class="font-bold text-brand-navy-900">{{ $enrollment->student->full_name }}</p><p class="mt-1 text-xs text-muted">{{ $enrollment->application_number }}</p></td>
                                <td class="whitespace-nowrap px-5 py-4"><p class="font-semibold">{{ $enrollment->gradeLevel->name }}</p><p class="mt-1 text-xs text-muted">{{ $enrollment->academicYear->name }}</p></td>
                                <td class="px-5 py-4">@if($recommendation?->section)<p class="font-bold text-brand-navy-900">{{ $recommendation->section->name }}</p><p class="mt-1 text-xs text-muted">{{ $recommendation->confirmedCount }}/{{ $recommendation->section->capacity }} assigned</p>@else<span class="text-sm font-semibold text-warning-800">No available section</span>@endif</td>
                                <td class="whitespace-nowrap px-5 py-4 text-muted">{{ $enrollment->approved_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-4 text-right"><button type="button" class="jca-button-secondary whitespace-nowrap" @click="openPlacement('{{ $enrollment->public_id }}')" aria-haspopup="dialog">Assign section</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-6"><x-ui.empty-state icon="sections" :title="$hasFilters ? 'No approved students match these filters' : 'Placement queue is clear'" description="Change the filters or wait for another enrollment to be approved." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($approvedEnrollments->hasPages())<div class="border-t border-border p-5 sm:px-6">{{ $approvedEnrollments->onEachSide(1)->links() }}</div>@endif
        </section>

        {{-- ==================== Current Assignments ==================== --}}
        <section
            id="current-assignments"
            x-show="activeTab === 'current-assignments'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="jca-card overflow-hidden"
            aria-labelledby="current-title"
        >
            <div class="flex flex-col gap-2 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div><h2 id="current-title" class="text-xl font-black text-brand-navy-900">Current confirmed assignments</h2><p class="mt-1 text-sm text-muted">Transfer one student at a time while preserving assignment history.</p></div>
                <p class="text-sm font-bold text-muted">{{ number_format($currentAssignments->total()) }} result{{ $currentAssignments->total() === 1 ? '' : 's' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Student</th><th class="px-5 py-3">Grade and year</th><th class="px-5 py-3">Current section</th><th class="px-5 py-3">Confirmed</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-border bg-white">
                        @forelse($currentAssignments as $assignment)
                            <tr class="transition-colors hover:bg-slate-50/60">
                                <td class="px-5 py-4"><p class="font-bold text-brand-navy-900">{{ $assignment->enrollment->student->full_name }}</p><p class="mt-1 text-xs text-muted">{{ $assignment->enrollment->application_number }}</p></td>
                                <td class="whitespace-nowrap px-5 py-4"><p class="font-semibold">{{ $assignment->enrollment->gradeLevel->name }}</p><p class="mt-1 text-xs text-muted">{{ $assignment->enrollment->academicYear->name }}</p></td>
                                <td class="px-5 py-4 font-bold text-brand-navy-900">{{ $assignment->section->name }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-muted">{{ $assignment->confirmed_at->format('d M Y') }}</td>
                                <td class="px-5 py-4">@if($assignment->is_capacity_override)<x-ui.status-badge tone="danger">Capacity override</x-ui.status-badge>@else<x-ui.status-badge tone="ready">Confirmed</x-ui.status-badge>@endif</td>
                                <td class="px-5 py-4 text-right"><button type="button" class="jca-button-secondary whitespace-nowrap" @click="openTransfer('{{ $assignment->public_id }}')" aria-haspopup="dialog">Transfer</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-6"><x-ui.empty-state icon="sections" :title="$hasFilters ? 'No assignments match these filters' : 'No current assignments'" description="Confirmed section placements will appear here." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($currentAssignments->hasPages())<div class="border-t border-border p-5 sm:px-6">{{ $currentAssignments->onEachSide(1)->links() }}</div>@endif
        </section>

        {{-- ==================== Transfer History ==================== --}}
        <section
            id="transfer-history"
            x-show="activeTab === 'transfer-history'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="jca-card overflow-hidden"
            aria-labelledby="history-title"
        >
            <div class="flex flex-col gap-2 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div><h2 id="history-title" class="text-xl font-black text-brand-navy-900">Transfer history</h2><p class="mt-1 text-sm text-muted">Ended assignments remain unchanged and traceable.</p></div>
                <p class="text-sm font-bold text-muted">{{ number_format($assignmentHistory->total()) }} result{{ $assignmentHistory->total() === 1 ? '' : 's' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Student</th><th class="px-5 py-3">Grade and year</th><th class="px-5 py-3">Previous section</th><th class="px-5 py-3">Ended</th><th class="px-5 py-3">Reason</th></tr></thead>
                    <tbody class="divide-y divide-border bg-white">
                        @forelse($assignmentHistory as $historical)
                            <tr class="transition-colors hover:bg-slate-50/60"><td class="px-5 py-4"><p class="font-bold text-brand-navy-900">{{ $historical->enrollment->student->full_name }}</p><p class="mt-1 text-xs text-muted">{{ $historical->enrollment->application_number }}</p></td><td class="whitespace-nowrap px-5 py-4"><p class="font-semibold">{{ $historical->enrollment->gradeLevel->name }}</p><p class="mt-1 text-xs text-muted">{{ $historical->enrollment->academicYear->name }}</p></td><td class="px-5 py-4 font-semibold">{{ $historical->section->name }}</td><td class="whitespace-nowrap px-5 py-4">{{ $historical->ended_at->format('d M Y, g:i A') }}</td><td class="max-w-md px-5 py-4 text-muted">{{ $historical->ended_reason }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="p-6"><x-ui.empty-state icon="audit" :title="$hasFilters ? 'No transfer history matches these filters' : 'No transfer history'" description="Completed transfers appear here without removing prior assignments." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($assignmentHistory->hasPages())<div class="border-t border-border p-5 sm:px-6">{{ $assignmentHistory->onEachSide(1)->links() }}</div>@endif
        </section>

        {{-- ==================== Promotion Review ==================== --}}
        <section
            id="promotion-review"
            x-show="activeTab === 'promotion-review'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="jca-card overflow-hidden"
            aria-labelledby="promotion-title"
        >
            <div class="flex flex-col gap-2 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div><h2 id="promotion-title" class="text-xl font-black text-brand-navy-900">Promotion review</h2><p class="mt-1 text-sm text-muted">A student is eligible when every current subject has a Registrar-approved final grade, a next grade level exists, and a future school year is configured. Reviewing never changes the completed school-year record.</p></div>
                <p class="text-sm font-bold text-muted">{{ number_format($enrolledForPromotion->total()) }} result{{ $enrolledForPromotion->total() === 1 ? '' : 's' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3 text-center">
                                <label class="inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded accent-brand-navy-700"
                                        @click="toggleAllPromotions()"
                                        :checked="allPromotionsSelected"
                                        :indeterminate="somePromotionsSelected"
                                        aria-label="Select all students on this page"
                                    >
                                </label>
                            </th>
                            <th class="px-5 py-3">Student</th>
                            <th class="px-5 py-3">Current grade and year</th>
                            <th class="px-5 py-3">Review status</th>
                            <th class="px-5 py-3">Recommended target</th>
                            <th class="px-5 py-3">Next-year draft</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-white">
                        @forelse($enrolledForPromotion as $enrollment)
                            @php($promotion = $enrollment->promotionRecommendation)
                            @php($justReviewed = session('promotion_reviewed') === $enrollment->public_id)
                            <tr
                                id="promotion-row-{{ $enrollment->public_id }}"
                                class="scroll-mt-32 transition-colors hover:bg-slate-50/60 {{ $justReviewed ? 'bg-brand-blue-100/50 shadow-[inset_4px_0_0_var(--color-brand-gold-500)]' : '' }}"
                                @if($justReviewed) data-just-reviewed @endif
                                :class="isPromotionSelected('{{ $enrollment->public_id }}') ? 'bg-brand-blue-100/30' : ''"
                            >
                                <td class="px-3 py-4 text-center">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded accent-brand-navy-700"
                                        @click="togglePromotion('{{ $enrollment->public_id }}')"
                                        :checked="isPromotionSelected('{{ $enrollment->public_id }}')"
                                        aria-label="Select {{ $enrollment->student->full_name }}"
                                    >
                                </td>
                                <td class="px-5 py-4"><p class="font-bold text-brand-navy-900">{{ $enrollment->student->full_name }}</p><p class="mt-1 text-xs text-muted">{{ $enrollment->application_number }}</p>@if($justReviewed)<p class="mt-1 text-xs font-bold text-brand-navy-700">Just updated</p>@endif</td>
                                <td class="whitespace-nowrap px-5 py-4"><p class="font-semibold">{{ $enrollment->gradeLevel->name }}</p><p class="mt-1 text-xs text-muted">{{ $enrollment->academicYear->name }}</p></td>
                                @php($gradeCheck = $promotionGrades[$enrollment->id] ?? null)
                                <td class="px-5 py-4">
                                    @if($promotion)<x-ui.status-badge :status="$promotion->eligibility_status" :label="$promotion->eligibility_status === 'grades_pending' ? 'Waiting for grades' : null" />@else<span class="font-semibold text-muted">Not reviewed</span>@endif
                                    @if($gradeCheck && $gradeCheck->requiredCount() > 0)
                                        <p class="mt-1.5 text-xs font-semibold {{ $gradeCheck->isReady() ? 'text-success-700' : 'text-warning-800' }}">Final grades approved: {{ $gradeCheck->approvedCount() }} / {{ $gradeCheck->requiredCount() }}</p>
                                    @elseif($gradeCheck && in_array($gradeCheck->state, ['no_section', 'no_subjects'], true))
                                        <p class="mt-1.5 text-xs font-semibold text-warning-800">{{ $gradeCheck->state === 'no_section' ? 'No confirmed section' : 'No subjects in section' }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4">{{ collect([$promotion?->targetGradeLevel?->name, $promotion?->targetAcademicYear?->name])->filter()->join(' · ') ?: '—' }}</td>
                                <td class="px-5 py-4">@if($promotion?->created_enrollment_id)<x-ui.status-badge tone="ready">Draft created</x-ui.status-badge>@else<span class="text-muted">Not created</span>@endif</td>
                                <td class="px-5 py-4 text-right"><button type="button" class="jca-button-secondary whitespace-nowrap" @click="openPromotion('{{ $enrollment->public_id }}')" aria-haspopup="dialog">Manage promotion</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-6"><x-ui.empty-state icon="progress" :title="$hasFilters ? 'No promotion records match these filters' : 'No promotion reviews available'" description="Enrolled students appear here. They become eligible once every subject has a Registrar-approved final grade and a future school year is configured." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($enrolledForPromotion->hasPages())<div class="border-t border-border p-5 sm:px-6">{{ $enrolledForPromotion->onEachSide(1)->links() }}</div>@endif

            {{-- Mass promotion action bar --}}
            <div
                x-show="hasPromotionSelection"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-4"
                class="sticky bottom-4 mx-4 mt-4 rounded-2xl border border-brand-navy-700/20 bg-brand-navy-900 px-5 py-3 text-white shadow-2xl sm:mx-6"
                x-cloak
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-white/10"><x-ui.icon name="check-all" size="size-5" /></span>
                        <div>
                            <p class="text-sm font-bold"><span x-text="selectedPromotions.length"></span> of {{ $enrolledForPromotion->total() }} selected</p>
                            <p class="text-xs text-white/60">Approve promotions in bulk. Ineligible or already-promoted students are skipped.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="selectAllEligiblePromotions()"
                            class="inline-flex min-h-[2.25rem] items-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-white/20"
                        >
                            <x-ui.icon name="check-all" size="size-4" />
                            Select all eligible
                        </button>
                        <form method="POST" action="{{ route('promotion.mass-recommend') }}" x-ref="massRecommendForm">
                            @csrf
                            <template x-for="id in selectedPromotions" :key="id">
                                <input type="hidden" name="enrollment_public_ids[]" :value="id">
                            </template>
                            <button
                                type="submit"
                                class="inline-flex min-h-[2.25rem] items-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-white/20"
                            >
                                <x-ui.icon name="progress" size="size-4" />
                                Check eligibility
                            </button>
                        </form>
                        <form method="POST" action="{{ route('promotion.mass-create-drafts') }}" x-ref="massCreateForm" @submit="confirmMassApprove($event)">
                            @csrf
                            <template x-for="id in selectedPromotions" :key="id">
                                <input type="hidden" name="enrollment_public_ids[]" :value="id">
                            </template>
                            <button
                                type="submit"
                                class="inline-flex min-h-[2.25rem] items-center gap-2 rounded-lg bg-brand-gold-500 px-4 py-2 text-sm font-black text-brand-navy-900 transition-colors hover:bg-yellow-400"
                            >
                                <x-ui.icon name="enrollment" size="size-4" />
                                Approve &amp; promote selected
                            </button>
                        </form>
                        <button
                            type="button"
                            @click="selectedPromotions = []"
                            class="inline-flex min-h-[2.25rem] items-center gap-1 rounded-lg px-3 py-2 text-sm font-bold text-white/70 transition-colors hover:text-white"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- ==================== Placement Dialog ==================== --}}
        <dialog x-ref="placementDialog" @cancel="closePlacement()" @click.self="closePlacement()" class="m-auto w-[min(42rem,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55" aria-labelledby="placement-dialog-title">
            <div x-show="selectedPlacement" x-cloak>
                <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-white p-5 sm:px-6"><div><p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Section placement</p><h2 id="placement-dialog-title" class="mt-1 text-xl font-black text-brand-navy-900" x-text="selectedPlacement?.name"></h2><p class="mt-1 text-sm text-muted"><span x-text="selectedPlacement?.reference"></span> · <span x-text="selectedPlacement?.context"></span></p></div><button type="button" class="grid size-10 place-items-center rounded-lg text-muted hover:bg-slate-100" @click="closePlacement()" aria-label="Close section placement"><x-ui.icon name="close" /></button></header>
                <form method="POST" :action="selectedPlacement?.action" class="space-y-5 p-5 sm:p-6">@csrf
                    <input type="hidden" name="modal_context" value="placement"><input type="hidden" name="record_public_id" :value="selectedPlacement?.publicId">
                    @if(old('modal_context') === 'placement' && $errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-brand-red-600" role="alert"><p class="font-bold">Please correct the placement details.</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <p class="rounded-xl bg-brand-blue-100 p-4 text-sm text-brand-navy-900" x-text="selectedPlacement?.recommendation"></p>
                    <label class="jca-label">Section<select name="section_public_id" required class="jca-input" x-model="placementSection"><option value="">Select an active matching section</option><template x-for="section in selectedPlacement?.sections ?? []" :key="section.value"><option :value="section.value" x-text="section.label"></option></template></select></label>
                    <template x-if="selectedPlacement?.supportReview"><label class="jca-check items-start rounded-xl border border-warning-200 bg-warning-50 p-4"><input type="checkbox" name="support_suitability_confirmed" value="1" x-model="supportConfirmed"> I confirm this section is suitable based on the authorized school review.</label></template>
                    @if(auth()->user()->hasRole('admin'))<details class="rounded-xl border border-red-200 bg-red-50 p-4"><summary class="cursor-pointer font-bold text-brand-red-600">Admin capacity override</summary><label class="jca-check mt-3"><input type="checkbox" name="override_capacity" value="1" x-model="capacityOverride"> Override configured capacity</label><label class="jca-label mt-3">Override reason<textarea name="override_reason" rows="2" class="jca-input" x-model="overrideReason"></textarea></label></details>@endif
                    <div class="flex justify-end gap-3"><button type="button" class="jca-button-secondary" @click="closePlacement()">Cancel</button><x-ui.button type="submit"><x-ui.icon name="sections" /> Confirm section and enroll</x-ui.button></div>
                </form>
            </div>
        </dialog>

        {{-- ==================== Transfer Dialog ==================== --}}
        <dialog x-ref="transferDialog" @cancel="closeTransfer()" @click.self="closeTransfer()" class="m-auto w-[min(42rem,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55" aria-labelledby="transfer-dialog-title">
            <div x-show="selectedAssignment" x-cloak>
                <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-white p-5 sm:px-6"><div><p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Transfer with history</p><h2 id="transfer-dialog-title" class="mt-1 text-xl font-black text-brand-navy-900" x-text="selectedAssignment?.name"></h2><p class="mt-1 text-sm text-muted">Current section: <strong x-text="selectedAssignment?.currentSection"></strong> · <span x-text="selectedAssignment?.context"></span></p></div><button type="button" class="grid size-10 place-items-center rounded-lg text-muted hover:bg-slate-100" @click="closeTransfer()" aria-label="Close transfer"><x-ui.icon name="close" /></button></header>
                <form method="POST" :action="selectedAssignment?.action" class="space-y-5 p-5 sm:p-6">@csrf
                    <input type="hidden" name="modal_context" value="transfer"><input type="hidden" name="record_public_id" :value="selectedAssignment?.publicId">
                    @if(old('modal_context') === 'transfer' && $errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-brand-red-600" role="alert"><p class="font-bold">Please correct the transfer details.</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <template x-if="selectedAssignment?.capacityOverride"><p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-brand-red-600"><strong>Current placement used an authorized capacity override:</strong> <span x-text="selectedAssignment?.capacityOverrideReason"></span></p></template>
                    <label class="jca-label">Destination section<select name="section_public_id" required class="jca-input" x-model="transferSection"><option value="">Select destination</option><template x-for="section in selectedAssignment?.sections ?? []" :key="section.value"><option :value="section.value" x-text="section.label"></option></template></select></label>
                    <label class="jca-label">Transfer reason<textarea name="reason" required rows="3" maxlength="2000" class="jca-input" x-model="transferReason"></textarea></label>
                    <template x-if="selectedAssignment?.supportReview"><label class="jca-check items-start rounded-xl border border-warning-200 bg-warning-50 p-4"><input type="checkbox" name="support_suitability_confirmed" value="1" x-model="supportConfirmed"> I confirm destination suitability based on the authorized school review.</label></template>
                    @if(auth()->user()->hasRole('admin'))<details class="rounded-xl border border-red-200 bg-red-50 p-4"><summary class="cursor-pointer font-bold text-brand-red-600">Admin capacity override</summary><label class="jca-check mt-3"><input type="checkbox" name="override_capacity" value="1" x-model="capacityOverride"> Override configured capacity</label><label class="jca-label mt-3">Override reason<textarea name="override_reason" rows="2" class="jca-input" x-model="overrideReason"></textarea></label></details>@endif
                    <div class="flex justify-end gap-3"><button type="button" class="jca-button-secondary" @click="closeTransfer()">Cancel</button><x-ui.button type="submit"><x-ui.icon name="audit" /> Transfer with history</x-ui.button></div>
                </form>
            </div>
        </dialog>

        {{-- ==================== Promotion Dialog ==================== --}}
        <dialog x-ref="promotionDialog" @cancel="closePromotion()" @click.self="closePromotion()" class="m-auto w-[min(40rem,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55" aria-labelledby="promotion-dialog-title">
            <div x-show="selectedPromotion" x-cloak>
                <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-white p-5 sm:px-6"><div><p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Promotion review</p><h2 id="promotion-dialog-title" class="mt-1 text-xl font-black text-brand-navy-900" x-text="selectedPromotion?.name"></h2><p class="mt-1 text-sm text-muted"><span x-text="selectedPromotion?.reference"></span> · <span x-text="selectedPromotion?.context"></span></p></div><button type="button" class="grid size-10 place-items-center rounded-lg text-muted hover:bg-slate-100" @click="closePromotion()" aria-label="Close promotion review"><x-ui.icon name="close" /></button></header>
                <div class="space-y-5 p-5 sm:p-6">
                    <template x-if="selectedPromotion?.reason"><div class="rounded-xl bg-slate-50 p-4 text-sm"><p class="font-bold text-brand-navy-900">Current review: <span x-text="selectedPromotion?.statusLabel"></span></p><p class="mt-1 text-muted" x-text="selectedPromotion?.reason"></p><p class="mt-2 font-semibold" x-show="selectedPromotion?.target">Target: <span x-text="selectedPromotion?.target"></span></p></div></template>
                    <template x-if="selectedPromotion?.grades && selectedPromotion.grades.subjects.length">
                        <div class="rounded-xl border border-border">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
                                <p class="text-sm font-black text-brand-navy-900">Final grades</p>
                                <p class="text-xs font-bold" :class="selectedPromotion.grades.ready ? 'text-success-700' : 'text-warning-800'"><span x-text="selectedPromotion.grades.approved"></span> of <span x-text="selectedPromotion.grades.required"></span> approved</p>
                            </div>
                            <ul class="divide-y divide-border text-sm">
                                <template x-for="subject in selectedPromotion.grades.subjects" :key="subject.code">
                                    <li class="flex items-center justify-between gap-3 px-4 py-2">
                                        <span class="min-w-0"><span class="font-semibold" x-text="subject.name"></span> <span class="text-xs" :class="subject.status === 'approved' ? 'text-success-700' : 'text-warning-800'" x-text="'· ' + subject.label"></span></span>
                                        <span class="font-black tabular-nums text-brand-navy-900" x-text="subject.final_grade ?? '—'"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                    <template x-if="selectedPromotion?.grades && ! selectedPromotion.grades.subjects.length && ! selectedPromotion.grades.ready"><p class="rounded-xl bg-warning-50 p-4 text-sm font-semibold text-warning-800" x-text="selectedPromotion.grades.summary"></p></template>
                    <template x-if="selectedPromotion?.staleEligible"><p class="rounded-xl bg-warning-50 p-4 text-sm font-semibold text-warning-800">Final grades changed since the last review. Review eligibility again before creating the next-year draft.</p></template>
                    <form method="POST" :action="selectedPromotion?.recommendAction" class="space-y-4">@csrf
                        <input type="hidden" name="modal_context" value="promotion"><input type="hidden" name="record_public_id" :value="selectedPromotion?.publicId">
                        @if(old('modal_context') === 'promotion' && $errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-brand-red-600" role="alert"><p class="font-bold">Please correct the promotion details.</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                        <label class="jca-label">Target school year<select name="target_academic_year_public_id" class="jca-input" x-model="targetYear"><option value="">Use earliest configured future year</option><template x-for="year in selectedPromotion?.years ?? []" :key="year.value"><option :value="year.value" x-text="year.label"></option></template></select></label>
                        <div class="flex justify-end"><x-ui.button type="submit" variant="secondary"><x-ui.icon name="progress" /> Review eligibility</x-ui.button></div>
                    </form>
                    <template x-if="selectedPromotion?.createAction"><form method="POST" :action="selectedPromotion?.createAction" class="border-t border-border pt-5">@csrf<p class="text-sm text-muted">All final grades are approved and eligibility is confirmed. This creates a separate next-year draft and preserves the current record.</p><div class="mt-4 flex justify-end"><x-ui.button type="submit"><x-ui.icon name="enrollment" /> Create next-year draft</x-ui.button></div></form></template>
                    <template x-if="selectedPromotion?.draftCreated"><p class="rounded-xl bg-success-50 p-4 text-sm font-bold text-success-700">The next-year draft has already been created for Registrar review.</p></template>
                    <div class="flex justify-end border-t border-border pt-4"><button type="button" class="jca-button-secondary" @click="closePromotion()">Close</button></div>
                </div>
            </div>
        </dialog>
    </div>
</x-layouts.app>
