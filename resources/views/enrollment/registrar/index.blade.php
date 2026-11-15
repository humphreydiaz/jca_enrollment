<x-layouts.app title="Enrollment review">
    @php
        $rowRecords = $enrollments->getCollection()->map(fn ($enrollment) => ['publicId' => $enrollment->public_id])->values();
        $bulkStatuses = [
            'under_review' => 'Move to under review',
            'approved' => 'Approve',
            'waitlisted' => 'Waitlist',
            'incomplete' => 'Mark incomplete',
            'rejected' => 'Reject',
        ];
        $hasFilters = request('search') || request('status') || request('grade');
        $reviewEnrollment = session('review_enrollment') ?? old('review_enrollment');
        $reviewRequirementId = session('review_requirement_id') ?? old('review_requirement_id');
    @endphp
    <div class="space-y-6" x-data="enrollmentReviewWorkspace(@js($rowRecords), @js($reviewEnrollment), @js($reviewRequirementId))">
        {{-- Page header --}}
        <x-ui.page-header
            title="Enrollment review queue"
            description="Issue access after an in-person identity check, then review authenticated applications and requirement snapshots. Payment and section placement remain separate."
            eyebrow="Registrar workspace"
        />
        <div class="flex justify-end">
            <a href="{{ route('enrollment.registrar.issue-access.create') }}" class="jca-button jca-button-primary">
                <x-ui.icon name="people" size="size-4" />
                Issue walk-in access
            </a>
        </div>
        @if($errors->any())
            <section class="rounded-xl border border-red-200 bg-red-50 p-4 sm:p-5" role="alert" aria-labelledby="enrollment-action-error-title">
                <h2 id="enrollment-action-error-title" class="font-black text-brand-red-600">The requested account action could not be completed</h2>
                <p class="mt-1 text-sm leading-6 text-brand-red-600">{{ $errors->first() }}</p>
            </section>
        @endif
        {{-- Reference-matched enrollment summary + filters. Changes below are VISUAL ONLY. --}}
        <style>
            /* Scoped to this enrollment view. No Tailwind build or global style changes required. */
            .jca-er-stats {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
            }
            .jca-er-stat {
                display: flex;
                align-items: center;
                gap: 13px;
                min-width: 0;
                min-height: 74px;
                padding: 14px 17px;
                background: #fff;
                border: 1px solid #dfe2ed;
                border-radius: 10px;
                box-shadow: 0 2px 4px rgba(29, 32, 83, .055);
            }
            .jca-er-stat-icon {
                width: 36px;
                height: 36px;
                flex: 0 0 36px;
                display: flex;
                justify-content: center;
                align-items: center;
                border-radius: 8px;
                background: #eef0ff;
                color: #6665b7;
            }
            .jca-er-stat-icon svg { width: 18px; height: 18px; }
            .jca-er-stat-number {
                display: block;
                color: #161348;
                font-size: 24px;
                font-weight: 800;
                line-height: 1.1;
                font-variant-numeric: tabular-nums;
            }
            .jca-er-stat-label {
                display: block;
                margin-top: 3px;
                color: #73778e;
                font-size: 14px;
                font-weight: 500;
                line-height: 1.25;
            }
            .jca-er-filter-shell {
                overflow: hidden;
                background: #fff;
                border: 1px solid #dfe2ed;
                border-radius: 11px;
                box-shadow: 0 2px 4px rgba(29, 32, 83, .045);
            }
            .jca-er-filter-form {
                display: grid;
                grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
                align-items: end;
                gap: 10px;
                padding: 16px 18px;
            }
            .jca-er-filter-label {
                display: block;
                min-width: 0;
                color: #292641;
                font-size: 14px;
                font-weight: 700;
                line-height: 19px;
            }
            .jca-er-filter-input {
                display: block;
                width: 100%;
                min-width: 0;
                height: 40px;
                margin-top: 4px;
                padding: 0 12px;
                color: #25243d;
                background: #fff;
                border: 1px solid #cdd4e5;
                border-radius: 7px;
                font: inherit;
                font-size: 14px;
                font-weight: 400;
                line-height: normal;
            }
            .jca-er-filter-input::placeholder { color: #8b92ad; opacity: 1; }
            .jca-er-filter-input:focus-visible {
                outline: 2px solid #a7a2ed;
                outline-offset: 1px;
                border-color: #5545ad;
            }
            .jca-er-filter-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 7px;
                height: 40px;
                min-width: 76px;
                padding: 0 13px;
                background: #2a226d;
                color: #fff;
                border: 1px solid #2a226d;
                border-radius: 7px;
                font-size: 14px;
                font-weight: 700;
                white-space: nowrap;
                cursor: pointer;
                transition: background .15s ease;
            }
            .jca-er-filter-button:hover { background: #3b318b; }
            .jca-er-filter-button:focus-visible { outline: 2px solid #a7a2ed; outline-offset: 2px; }
            .jca-er-filter-button svg { width: 14px; height: 14px; }
            .jca-er-filter-actions { display: flex; align-items: end; gap: 8px; }
            .jca-er-clear-button {
                display: inline-flex;
                justify-content: center;
                align-items: center;
                height: 40px;
                padding: 0 12px;
                border: 1px solid #cdd4e5;
                border-radius: 7px;
                background: #fff;
                color: #39365e;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                white-space: nowrap;
            }
            .jca-er-clear-button:hover { background: #f6f6fa; }
            .jca-er-active-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 18px; border-top: 1px solid #e5e8f2; font-size: 13px; color: #73778e; }
            .jca-er-filter-chip { display: inline-flex; gap: 5px; border-radius: 999px; background: #eef0ff; padding: 4px 10px; color: #393276; font-weight: 600; }
            @media (max-width: 900px) {
                .jca-er-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .jca-er-filter-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (max-width: 540px) {
                .jca-er-stats { gap: 10px; }
                .jca-er-stat { padding: 12px; min-height: 70px; gap: 9px; }
                .jca-er-stat-icon { width: 32px; height: 32px; flex-basis: 32px; }
                .jca-er-stat-number { font-size: 22px; }
                .jca-er-stat-label { font-size: 13px; }
                .jca-er-filter-form { grid-template-columns: minmax(0, 1fr); padding: 14px; }
                .jca-er-filter-actions, .jca-er-filter-button { width: 100%; }
            }
        </style>

        {{-- Four cards in the exact reference order; original dynamic counts preserved. --}}
        <section class="jca-er-stats" aria-label="Enrollment review summary">
            @foreach([
                ['label' => 'Total applications', 'value' => $summary['total'], 'icon' => 'inbox'],
                ['label' => 'Needs review', 'value' => $summary['needs_review'], 'icon' => 'progress'],
                ['label' => 'Waitlisted', 'value' => $summary['waitlisted'], 'icon' => 'audit'],
                ['label' => 'Approved', 'value' => $summary['approved'], 'icon' => 'support'],
            ] as $stat)
                <div class="jca-er-stat">
                    <span class="jca-er-stat-icon"><x-ui.icon :name="$stat['icon']" size="size-4" /></span>
                    <span class="min-w-0">
                        <span class="jca-er-stat-number">{{ number_format($stat['value']) }}</span>
                        <span class="jca-er-stat-label">{{ $stat['label'] }}</span>
                    </span>
                </div>
            @endforeach
        </section>

        {{-- One horizontal search / status / grade / violet filter bar as in screenshot. --}}
        <section class="jca-er-filter-shell" aria-label="Filter applications">
            <form method="GET" class="jca-er-filter-form">
                <label class="jca-er-filter-label">
                    Search applications
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Reference number or student name..." class="jca-er-filter-input" />
                </label>
                <label class="jca-er-filter-label">
                    Status
                    <select name="status" class="jca-er-filter-input">
                        <option value="">All statuses</option>
                        @foreach(['draft','submitted','under_review','incomplete','assessment_required','assessment_pending','approved','waitlisted','rejected','withdrawn','cancelled'] as $filterStatus)
                            <option value="{{ $filterStatus }}" @selected(request('status') === $filterStatus)>{{ ucfirst(str_replace('_', ' ', $filterStatus)) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="jca-er-filter-label">
                    Grade level
                    <select name="grade" class="jca-er-filter-input">
                        <option value="">All grades</option>
                        @foreach($filterGrades as $grade)
                            <option value="{{ $grade->public_id }}" @selected(request('grade') === $grade->public_id)>{{ $grade->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="jca-er-filter-actions">
                    <button type="submit" class="jca-er-filter-button"><x-ui.icon name="search" size="size-4" /> Filter</button>
                    @if($hasFilters)
                        <a href="{{ route('enrollment.registrar.index') }}" class="jca-er-clear-button">Clear</a>
                    @endif
                </div>
            </form>
            @if($hasFilters)
                <div class="jca-er-active-filters">
                    <span class="font-semibold">Active filters:</span>
                    @if(request('search'))
                        <span class="jca-er-filter-chip">Search: "{{ request('search') }}"</span>
                    @endif
                    @if(request('status'))
                        <span class="jca-er-filter-chip">Status: {{ ucfirst(str_replace('_', ' ', request('status'))) }}</span>
                    @endif
                    @if(request('grade'))
                        <span class="jca-er-filter-chip">Grade: {{ $filterGrades->firstWhere('public_id', request('grade'))?->name }}</span>
                    @endif
                </div>
            @endif
        </section>

        {{-- Results table --}}
        <section class="jca-card overflow-hidden" aria-label="Applications">
            <div class="flex items-center justify-between border-b border-border px-5 py-4 sm:px-6">
                <p class="text-sm text-muted">
                    <span class="font-bold text-ink">{{ $enrollments->total() }}</span>
                    {{ Str::plural('application', $enrollments->total()) }} found
                    @if($enrollments->hasPages())
                          Showing {{ $enrollments->firstItem() }}-{{ $enrollments->lastItem() }}
                    @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3 text-center">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded accent-brand-navy-700"
                                    @click="toggleAll()"
                                    :checked="allSelected"
                                    :indeterminate="someSelected"
                                    aria-label="Select all applications on this page"
                                >
                            </th>
                            <th class="px-5 py-3">Student</th>
                            <th class="px-5 py-3">Grade and year</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Identity</th>
                            <th class="px-5 py-3">Requirements</th>
                            <th class="px-5 py-3">Submitted</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-white">
                        @forelse($enrollments as $enrollment)
                            @php
                                $nextStatuses = $allowedTransitions->get($enrollment->id, []);
                                $lateClosingDate = $enrollment->academicYear?->enrollment_closes_on
                                    ? \Illuminate\Support\Carbon::parse($enrollment->academicYear->enrollment_closes_on)->toDateString()
                                    : null;
                                $isLateDraft = $enrollment->status === 'draft'
                                    && $lateClosingDate !== null
                                    && now('Asia/Manila')->toDateString() > $lateClosingDate;
                                $hasLateApproval = $enrollment->late_enrollment_approved_at !== null;
                                $reqTotal = $enrollment->requirements->count();
                                $reqVerified = $enrollment->requirements->filter(fn ($requirement) =>
                                    $requirement->soft_copy_status === 'verified'
                                    || $requirement->status === 'waived'
                                    || $requirement->status === 'verified'
                                    || ($requirement->status === 'submitted' && $requirement->received_at !== null)
                                )->count();
                            @endphp
                            {{-- Summary row --}}
                            <tr
                                class="transition-colors hover:bg-slate-50/60"
                                :class="isSelected('{{ $enrollment->public_id }}') ? 'bg-brand-blue-100/30' : ''"
                            >
                                <td class="px-3 py-4 text-center align-top">
                                    <input
                                        type="checkbox"
                                        class="mt-1 size-4 rounded accent-brand-navy-700"
                                        @click="toggle('{{ $enrollment->public_id }}')"
                                        :checked="isSelected('{{ $enrollment->public_id }}')"
                                        aria-label="Select {{ $enrollment->student->full_name }}"
                                    >
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-blue-100 text-sm font-black text-brand-navy-700">
                                            {{ strtoupper(substr($enrollment->student->first_name ?? 'S', 0, 1)) }}{{ strtoupper(substr($enrollment->student->last_name ?? '', 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-bold text-brand-navy-900">{{ $enrollment->student->full_name }}</p>
                                            <p class="mt-0.5 text-xs text-muted">{{ $enrollment->application_number }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 align-top">
                                    <p class="font-semibold">{{ $enrollment->gradeLevel->name }}</p>
                                    <p class="mt-1 text-xs text-muted">{{ $enrollment->academicYear->name ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="flex flex-col items-start gap-1">
                                        <x-ui.status-badge :status="$enrollment->status" />
                                        @if (\App\Modules\People\Support\StudentExitOptions::isExited($enrollment->student?->status))
                                            <x-ui.status-badge :status="$enrollment->student->status" :label="\App\Modules\People\Support\StudentExitOptions::exitLabel($enrollment->student->status)" />
                                        @endif
                                        <x-ui.status-badge :status="$enrollment->student_category" tone="info" variant="outline" />
                                        @if($enrollment->is_late_enrollment)
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-900">
                                                Late: {{ ucfirst($enrollment->late_enrollment_status ?? 'pending') }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="flex flex-col items-start gap-1">
                                        <x-ui.status-badge :status="$enrollment->walk_in_verified_at || $enrollment->previous_enrollment_id ? 'verified' : 'legacy'" :label="$enrollment->walk_in_verified_at ? null : ($enrollment->previous_enrollment_id ? 'Continuing' : null)" />
                                        <x-ui.status-badge :status="$enrollment->duplicate_review_status" />
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    @if($reqTotal)
                                        <div class="flex items-center gap-2 text-xs">
                                            <div class="flex flex-col gap-1">
    <span class="font-bold text-brand-navy-900">{{ $reqVerified }}/{{ $reqTotal }}</span>
    @if($reqVerified === $reqTotal)
        <span class="text-xs font-bold text-emerald-700">Submitted</span>
    @elseif($reqVerified > 0)
        <span class="text-xs font-bold text-amber-700">Incomplete</span>
    @else
        <span class="text-xs font-bold text-red-700">Missing</span>
    @endif
</div>
                                            <div class="h-2 w-16 overflow-hidden rounded-full bg-gray-200">
                                                <div class="h-full rounded-full {{ $reqVerified === $reqTotal ? 'bg-success-700' : 'bg-brand-gold-500' }}" style="width: {{ round(($reqVerified / $reqTotal) * 100) }}%"></div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-muted">None</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 align-top text-muted">{{ $enrollment->submitted_at?->format('d M Y') ?? '-' }}</td>
                                <td class="px-5 py-4 text-right align-top">
                                    <button
                                        type="button"
                                        class="jca-button-secondary whitespace-nowrap"
                                        @click="toggleRow('{{ $enrollment->public_id }}')"
                                        :aria-expanded="isRowOpen('{{ $enrollment->public_id }}') ? 'true' : 'false'"
                                    >
                                        <span x-text="isRowOpen('{{ $enrollment->public_id }}') ? 'Hide' : 'Review'"></span>
                                        <x-ui.icon name="arrow-right" size="size-4" class="transition-transform" ::class="isRowOpen('{{ $enrollment->public_id }}') ? 'rotate-90' : ''" />
                                    </button>
                                </td>
                            </tr>
                            {{-- Expandable detail row --}}
                            <tr id="enrollment-review-{{ $enrollment->public_id }}" x-show="isRowOpen('{{ $enrollment->public_id }}')" x-cloak>
                                <td colspan="8" class="bg-canvas/40 px-5 py-5 sm:px-6">
                                    <div class="grid gap-6 xl:grid-cols-2">
                                        {{-- Walk-in identity and account ownership --}}
                                        <section class="rounded-xl border border-border bg-white p-4">
                                            <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-brand-navy-900">
                                                <x-ui.icon name="users" size="size-4" />
                                                Walk-in access and account ownership
                                            </h3>
                                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                                                <span class="flex items-center gap-1.5">
                                                    <span class="text-muted">In-person review</span>
                                                    <x-ui.status-badge :status="$enrollment->walk_in_verified_at || $enrollment->previous_enrollment_id ? 'verified' : 'legacy'" :label="$enrollment->walk_in_verified_at ? null : ($enrollment->previous_enrollment_id ? 'Continuing' : null)" />
                                                </span>
                                                <span class="flex items-center gap-1.5">
                                                    <span class="text-muted">Duplicate review</span>
                                                    <x-ui.status-badge :status="$enrollment->duplicate_review_status" />
                                                </span>
                                            </div>
                                            <div class="mt-4 flex flex-wrap gap-2">
                                                @if($enrollment->walk_in_verified_at)
                                                    <div class="rounded-lg border border-brand-blue-100 bg-brand-blue-50 px-3 py-2 text-sm text-brand-navy-900">
                                                        <strong>Student ID:</strong> {{ $enrollment->student->student_number }}
                                                          <strong>Access owner:</strong> {{ $enrollment->accessOwner?->name ?? 'Unavailable' }}
                                                          <strong>Issued:</strong> {{ $enrollment->access_issued_at?->timezone('Asia/Manila')->format('d M Y, g:i A') }}
                                                    </div>
                                                    @if($enrollment->status === 'draft' && $enrollment->accessOwner?->must_change_password)
                                                        <form method="POST" action="{{ route('enrollment.registrar.issue-access.reissue', $enrollment) }}">
                                                            @csrf
                                                            <x-ui.button type="submit" variant="secondary">Replace temporary password</x-ui.button>
                                                        </form>
                                                    @endif
                                                @elseif($enrollment->previous_enrollment_id)
                                                    <p class="text-sm text-muted">Continuing student, promoted from {{ $enrollment->previousEnrollment?->gradeLevel?->name }} - {{ $enrollment->previousEnrollment?->academicYear?->name }}. Identity was verified through that enrollment, so no new walk-in access is needed - the Registrar submits this application.</p>
                                                @else
                                                    <p class="text-sm text-muted">Legacy application created before the walk-in access workflow.</p>
                                                @endif
                                                @if($enrollment->student_category === 'support_review')
                                                    <form method="POST" action="{{ route('support.create', $enrollment) }}">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="secondary">
                                                            <x-ui.icon name="support" size="size-4" />
                                                            Open restricted support case
                                                        </x-ui.button>
                                                    </form>
                                                @endif
                                            </div>
                                            @if($enrollment->duplicate_review_status === 'pending')
                                                <div class="mt-4 space-y-3 border-t border-border pt-4">
                                                    <p class="text-xs font-bold uppercase tracking-wider text-muted">Duplicate resolution</p>
                                                    <form method="POST" action="{{ route('enrollment.registrar.duplicate-review', $enrollment) }}" class="flex flex-wrap items-end gap-2">
                                                        @csrf
                                                        <input type="hidden" name="duplicate_decision" value="clear">
                                                        <div class="min-w-0 flex-1">
                                                            <label for="clear_reason_{{ $enrollment->public_id }}" class="block text-xs font-semibold text-muted">Clearance reason</label>
                                                            <input id="clear_reason_{{ $enrollment->public_id }}" name="reason" required placeholder="Identities are distinct because..." class="jca-input" />
                                                        </div>
                                                        <x-ui.button type="submit" variant="secondary">Clear all candidates</x-ui.button>
                                                    </form>
                                                    @if($enrollment->potentialDuplicateStudent)
                                                        <form method="POST" action="{{ route('enrollment.registrar.duplicate-review', $enrollment) }}" class="flex flex-wrap items-end gap-2">
                                                            @csrf
                                                            <input type="hidden" name="duplicate_decision" value="link_existing">
                                                            <input type="hidden" name="student_public_id" value="{{ $enrollment->potentialDuplicateStudent->public_id }}">
                                                            <div class="min-w-0 flex-1">
                                                                <label for="link_student_{{ $enrollment->public_id }}" class="block text-xs font-semibold text-muted">Match evidence</label>
                                                                <input id="link_student_{{ $enrollment->public_id }}" name="reason" required placeholder="Student match evidence..." class="jca-input" />
                                                            </div>
                                                            <x-ui.button type="submit" variant="secondary">
                                                                Link student: {{ $enrollment->potentialDuplicateStudent->full_name }}
                                                            </x-ui.button>
                                                        </form>
                                                    @endif
                                                    @if($enrollment->potentialDuplicateGuardian)
                                                        <form method="POST" action="{{ route('enrollment.registrar.duplicate-review', $enrollment) }}" class="flex flex-wrap items-end gap-2">
                                                            @csrf
                                                            <input type="hidden" name="duplicate_decision" value="link_existing">
                                                            <input type="hidden" name="guardian_public_id" value="{{ $enrollment->potentialDuplicateGuardian->public_id }}">
                                                            <div class="min-w-0 flex-1">
                                                                <label for="link_guardian_{{ $enrollment->public_id }}" class="block text-xs font-semibold text-muted">Match evidence</label>
                                                                <input id="link_guardian_{{ $enrollment->public_id }}" name="reason" required placeholder="Guardian match evidence..." class="jca-input" />
                                                            </div>
                                                            <x-ui.button type="submit" variant="secondary">
                                                                Link guardian: {{ $enrollment->potentialDuplicateGuardian->full_name }}
                                                            </x-ui.button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endif
                                        </section>
                                        {{-- Controlled status transition --}}
                                        <section class="rounded-xl border border-border bg-white p-4">
                                            <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-brand-navy-900">
                                                <x-ui.icon name="progress" size="size-4" />
                                                Controlled status transition
                                            </h3>
                                            @if($draftMissing->has($enrollment->id))
                                                <div class="mt-4 rounded-lg bg-warning-100 px-4 py-3 text-sm text-warning-800">
                                                    <p class="font-bold">Waiting for the application form</p>
                                                    <p class="mt-1">{{ $enrollment->accessOwner?->name ?? 'The access owner' }} has not filled in the application yet, so it cannot be marked Submitted. They sign in, complete the form, and press Submit. Still missing: {{ implode(', ', $draftMissing->get($enrollment->id)) }}.</p>
                                                </div>
                                            @endif
                                            @if($nextStatuses !== [])
                                                <form method="POST" action="{{ route('enrollment.registrar.transition', $enrollment) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                                    @csrf
                                                    <div>
                                                        <label for="transition_status_{{ $enrollment->public_id }}" class="block text-xs font-semibold text-muted">New status</label>
                                                        <select id="transition_status_{{ $enrollment->public_id }}" name="status" required class="jca-input">
                                                            @foreach($nextStatuses as $nextStatus)
                                                                <option value="{{ $nextStatus }}">{{ str($nextStatus)->headline() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label for="transition_reason_{{ $enrollment->public_id }}" class="block text-xs font-semibold text-muted">Reason (when required)</label>
                                                        <input id="transition_reason_{{ $enrollment->public_id }}" name="reason" placeholder="Reason for this transition..." class="jca-input" />
                                                    </div>
                                                    <div class="self-end">
                                                        <x-ui.button type="submit" variant="primary">Apply</x-ui.button>
                                                    </div>
                                                </form>
                                            @else
                                                <div class="mt-4 flex items-center gap-2 rounded-lg bg-canvas px-4 py-3 text-sm text-muted">
                                                    <x-ui.icon name="support" size="size-4" />
                                                    No further status transition is available for the current state.
                                                </div>
                                            @endif
                                            {{-- Late enrollment exception: review actions stay inside each application --}}
                                            @if($isLateDraft || $enrollment->is_late_enrollment)
                                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                                                    <h4 class="text-base font-black text-amber-950">Late enrollment request</h4>
                                                    @if($lateClosingDate)
                                                        <p class="mt-1 text-sm leading-6 text-amber-900">
                                                            Regular enrollment deadline: {{ \Illuminate\Support\Carbon::parse($lateClosingDate)->format('F j, Y') }}.
                                                        </p>
                                                    @endif
                                                    @if($enrollment->late_enrollment_status === 'approved')
                                                        <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                                                            <strong>Late enrollment approved.</strong>
                                                            @if($enrollment->late_enrollment_reason)
                                                                <p class="mt-1">Reason: {{ $enrollment->late_enrollment_reason }}</p>
                                                            @endif
                                                            @if($enrollment->late_enrollment_approved_at)
                                                                <p class="mt-1">
                                                                    Approved {{ $enrollment->late_enrollment_approved_at->timezone('Asia/Manila')->format('d M Y, g:i A') }}
                                                                    @if($enrollment->lateEnrollmentApprover)
                                                                        by {{ $enrollment->lateEnrollmentApprover->name }}
                                                                    @endif
                                                                </p>
                                                            @endif
                                                        </div>
                                                    @elseif($enrollment->late_enrollment_status === 'rejected')
                                                        <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                                            <strong>Late enrollment rejected.</strong>
                                                            @if($enrollment->late_enrollment_reason)
                                                                <p class="mt-1">Reason: {{ $enrollment->late_enrollment_reason }}</p>
                                                            @endif
                                                        </div>
                                                    @elseif($enrollment->is_late_enrollment && $enrollment->late_enrollment_status === 'pending')
                                                        <p class="mt-2 text-sm text-amber-900">Pending Admin / Registrar decision. The application cannot be submitted until approved.</p>
                                                        @if(\Illuminate\Support\Facades\Route::has('enrollment.registrar.late-review') && auth()->user()->can('update', $enrollment))
                                                            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                                                <form method="POST" action="{{ route('enrollment.registrar.late-review', $enrollment) }}" class="space-y-2 rounded-lg border border-emerald-200 bg-white p-3">
                                                                    @csrf
                                                                    <input type="hidden" name="decision" value="approved">
                                                                    <label for="late_approve_reason_{{ $enrollment->public_id }}" class="block text-sm font-semibold">Approval reason (optional)</label>
                                                                    <textarea id="late_approve_reason_{{ $enrollment->public_id }}" name="reason" rows="2" maxlength="1000" class="jca-input w-full" placeholder="Reason for approving this request"></textarea>
                                                                    <button type="submit" class="jca-button jca-button-primary" onclick="return confirm('Approve this late enrollment request?')">Approve Late Enrollment</button>
                                                                </form>
                                                                <form method="POST" action="{{ route('enrollment.registrar.late-review', $enrollment) }}" class="space-y-2 rounded-lg border border-red-200 bg-white p-3">
                                                                    @csrf
                                                                    <input type="hidden" name="decision" value="rejected">
                                                                    <label for="late_reject_reason_{{ $enrollment->public_id }}" class="block text-sm font-semibold">Rejection reason (required)</label>
                                                                    <textarea id="late_reject_reason_{{ $enrollment->public_id }}" name="reason" rows="2" required maxlength="1000" class="jca-input w-full" placeholder="Reason for rejection"></textarea>
                                                                    <button type="submit" class="jca-button jca-button-secondary" onclick="return confirm('Reject this late enrollment request?')">Reject Late Enrollment</button>
                                                                </form>
                                                            </div>
                                                        @else
                                                            <p class="mt-3 text-sm text-amber-900">Approval controls are unavailable for this account.</p>
                                                        @endif
                                                    @else
                                                        <p class="mt-2 text-sm text-amber-900">The deadline has passed. The application must first be registered as a pending late request before review.</p>
                                                    @endif
                                                </div>
                                            @endif
                                            <div class="mt-4 border-t border-border pt-3">
                                                <p class="text-xs font-bold uppercase tracking-wider text-muted">Timeline</p>
                                                <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-muted">
                                                    <span>Created: <span class="font-semibold text-ink">{{ $enrollment->created_at->format('d M Y') }}</span></span>
                                                    @if($enrollment->submitted_at)
                                                        <span>Submitted: <span class="font-semibold text-ink">{{ $enrollment->submitted_at->format('d M Y') }}</span></span>
                                                    @endif
                                                    @if($enrollment->approved_at)
                                                        <span>Approved: <span class="font-semibold text-ink">{{ $enrollment->approved_at->format('d M Y') }}</span></span>
                                                    @endif
                                                    @if($enrollment->enrolled_at)
                                                        <span>Enrolled: <span class="font-semibold text-ink">{{ $enrollment->enrolled_at->format('d M Y') }}</span></span>
                                                    @endif
                                                    @if($enrollment->rejected_at)
                                                        <span>Rejected: <span class="font-semibold text-brand-red-600">{{ $enrollment->rejected_at->format('d M Y') }}</span></span>
                                                    @endif
                                                    @if($enrollment->withdrawn_at)
                                                        <span>Withdrawn: <span class="font-semibold text-muted">{{ $enrollment->withdrawn_at->format('d M Y') }}</span></span>
                                                    @endif
                                                </div>
                                            </div>
                                        </section>
                                    </div>
                                    {{-- Soft-copy evidence and continuing hard-copy obligation --}}
                                    <section class="mt-6 border-t border-border pt-5" aria-labelledby="requirements-{{ $enrollment->public_id }}">
                                        <div class="flex flex-wrap items-end justify-between gap-3">
                                            <div>
                                                <h3 id="requirements-{{ $enrollment->public_id }}" class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-brand-navy-900">
                                                    <x-ui.icon name="enrollment" size="size-4" />
                                                    Enrollment requirement evidence
                                                </h3>
                                                <p class="mt-1 text-xs text-muted">Admission needs a recorded hard-copy receipt (or an Admin waiver). Older soft copies stay viewable for reference only.</p>
                                            </div>
                                            @if($reqTotal)
                                                <div class="flex items-center gap-2 text-sm">
                                                    <div class="flex flex-col gap-1">
    <span class="font-bold text-brand-navy-900">{{ $reqVerified }}/{{ $reqTotal }}</span>
    @if($reqVerified === $reqTotal)
        <span class="text-xs font-bold text-emerald-700">Submitted</span>
    @elseif($reqVerified > 0)
        <span class="text-xs font-bold text-amber-700">Incomplete</span>
    @else
        <span class="text-xs font-bold text-red-700">Missing</span>
    @endif
</div>
                                                    <span class="text-muted">cleared</span>
                                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                                                        <div class="h-full rounded-full transition-all duration-300 {{ $reqVerified === $reqTotal ? 'bg-success-700' : 'bg-brand-gold-500' }}" style="width: {{ round(($reqVerified / $reqTotal) * 100) }}%"></div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                            @forelse($enrollment->requirements as $requirement)
                                                @php
                                                    $requirementLabel = $requirement->status === 'submitted' ? 'Hard Copy Received' : ($requirement->status === 'missing' ? 'Not Received' : null);
                                                    $reqCleared = in_array($requirement->status, ['verified', 'waived'], true);
                                                    $reqRejected = $requirement->status === 'rejected';
                                                    $reqIconName = $reqCleared ? 'support' : 'enrollment';
                                                    $reqIconBg = $reqCleared ? 'bg-green-50 text-success-700' : ($reqRejected ? 'bg-red-50 text-brand-red-600' : 'bg-gray-50 text-muted');
                                                @endphp
                                                <article id="requirement-card-{{ $requirement->id }}" class="rounded-xl border border-border bg-white p-4 shadow-sm transition-shadow hover:shadow-md">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <h4 class="flex items-center gap-2 font-semibold text-brand-navy-900">
                                                            <span class="grid size-7 place-items-center rounded-md {{ $reqIconBg }}">
                                                                <x-ui.icon :name="$reqIconName" size="size-3.5" />
                                                            </span>
                                                            {{ $requirement->documentType->name }}
                                                        </h4>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            @if($requirement->carried_from_requirement_id)
                                                                <x-ui.status-badge status="active" label="Carried forward" />
                                                            @endif
                                                            <x-ui.status-badge :status="$requirement->status" :label="$requirementLabel" />
                                                        </div>
                                                    </div>
                                                    @if($reviewEnrollment === $enrollment->public_id && (string) $reviewRequirementId === (string) $requirement->id && session('status'))
                                                        <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-success-700" role="status">{{ session('status') }}</p>
                                                    @endif
                                                    @if($requirement->carriedFromRequirement)
                                                        <p class="mt-3 rounded-lg bg-brand-blue-50 px-3 py-2 text-xs font-semibold leading-5 text-brand-navy-900">
                                                            Previously accepted for {{ collect([$requirement->carriedFromRequirement->enrollment?->gradeLevel?->name, $requirement->carriedFromRequirement->enrollment?->academicYear?->name])->filter()->join(' - ') }}. No resubmission is required.
                                                        </p>
                                                    @endif
                                                    <x-enrollment.registrar-soft-copy-review :requirement="$requirement" :enrollment-public-id="$enrollment->public_id" />
                                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4">
                                                        <div>
                                                            <p class="text-xs font-extrabold uppercase tracking-wider text-amber-900">Hard-copy obligation</p>
                                                            <p class="mt-1 text-xs text-muted">{{ $requirement->carried_from_requirement_id ? 'Previously accepted hard-copy evidence remains valid for this promotion.' : 'The physical document remains required even when the soft copy is verified.' }}</p>
                                                        </div>
                                                        @if($requirement->soft_copy_status === 'verified' && ! in_array($requirement->status, ['verified', 'waived'], true) && ! ($requirement->status === 'submitted' && $requirement->received_at !== null))
                                                            <x-ui.status-badge status="pending" label="Still outstanding" />
                                                        @endif
                                                    </div>
                                                    @if(in_array($requirement->status, ['missing', 'submitted', 'rejected'], true))
                                                        <form method="POST" action="{{ route('requirements.hard-copy.update', $requirement) }}" class="mt-3 space-y-3 border-t border-border pt-3">
                                                            @csrf @method('PUT')
                                                            <input type="hidden" name="review_enrollment" value="{{ $enrollment->public_id }}">
                                                            <input type="hidden" name="review_requirement_id" value="{{ $requirement->id }}">
                                                            <input type="hidden" name="received" value="0">
                                                            <label class="jca-check">
                                                                <input type="checkbox" name="received" value="1" @checked($requirement->status === 'submitted')>
                                                                Hard copy received by Registrar
                                                            </label>
                                                            <div>
                                                                <label for="receipt_notes_{{ $requirement->id }}" class="jca-label">Receipt note (optional)</label>
                                                                <input id="receipt_notes_{{ $requirement->id }}" class="jca-input" name="receipt_notes" maxlength="1000" value="{{ $requirement->receipt_notes }}" placeholder="Condition, copy type, or follow-up note">
                                                            </div>
                                                            <x-ui.button type="submit" variant="secondary">Save checklist item</x-ui.button>
                                                        </form>
                                                    @endif
                                                    @if($requirement->received_at)
                                                        <p class="mt-3 flex items-center gap-1 text-xs text-muted">
                                                            <x-ui.icon name="audit" size="size-3" />
                                                            Last received {{ $requirement->received_at->format('d M Y, g:i A') }} by {{ $requirement->receivedBy?->name ?? 'authorized staff' }}.
                                                        </p>
                                                    @endif
                                                    @if(in_array($requirement->status, ['submitted', 'verified'], true) || auth()->user()->hasRole('admin'))
                                                        <form method="POST" action="{{ route('requirements.review', $requirement) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-border pt-3">
                                                            @csrf
                                                            <input type="hidden" name="review_enrollment" value="{{ $enrollment->public_id }}">
                                                            <input type="hidden" name="review_requirement_id" value="{{ $requirement->id }}">
                                                            <div>
                                                                <label for="review_status_{{ $requirement->id }}" class="block text-xs font-semibold text-muted">Review decision</label>
                                                                <select id="review_status_{{ $requirement->id }}" name="status" required class="jca-input">
                                                                    @if(in_array($requirement->status, ['submitted', 'verified'], true))
                                                                        <option value="verified">Verify hard copy</option>
                                                                        <option value="rejected">Reject / request replacement</option>
                                                                    @endif
                                                                    @if(auth()->user()->hasRole('admin'))
                                                                        <option value="waived">Waive requirement</option>
                                                                    @endif
                                                                </select>
                                                            </div>
                                                            <div class="min-w-0 flex-1">
                                                                <label for="review_reason_{{ $requirement->id }}" class="block text-xs font-semibold text-muted">Reason</label>
                                                                <input id="review_reason_{{ $requirement->id }}" name="reason" placeholder="Reason when rejecting or waiving..." class="jca-input" />
                                                            </div>
                                                            <x-ui.button type="submit" variant="secondary">Save review</x-ui.button>
                                                        </form>
                                                    @endif
                                                </article>
                                            @empty
                                                <div class="lg:col-span-2">
                                                    <x-ui.empty-state icon="enrollment" title="No requirements configured" description="No requirement rules were configured when this application was submitted." />
                                                </div>
                                            @endforelse
                                        </div>
                                    </section>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-6">
                                    <x-ui.empty-state icon="inbox" title="No applications found" description="No applications match the current filter criteria. Try adjusting your search, status, or grade filter." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($enrollments->hasPages())
                <div class="border-t border-border p-5 sm:px-6">{{ $enrollments->onEachSide(1)->links() }}</div>
            @endif
        </section>
        {{-- Sticky bulk action bar --}}
        <form
            method="POST"
            action="{{ route('enrollment.registrar.bulk-transition') }}"
            x-show="hasSelection"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-4"
            class="sticky bottom-4 z-20 rounded-2xl border border-brand-navy-700/20 bg-brand-navy-900 px-5 py-3 text-white shadow-2xl"
            @submit="confirmBulk($event)"
            x-cloak
        >
            @csrf
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="enrollment_public_ids[]" :value="id">
            </template>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-xl bg-white/10"><x-ui.icon name="check-all" size="size-5" /></span>
                    <div>
                        <p class="text-sm font-bold"><span x-text="selected.length"></span> of {{ $enrollments->total() }} selected</p>
                        <p class="text-xs text-white/60">Apply a status change to every selected application. Ineligible ones are skipped.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="text-xs font-semibold text-white/70">
                        <span class="mb-1 block">New status</span>
                        <select name="status" required class="jca-input min-w-[12rem] text-ink">
                            @foreach($bulkStatuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-white/70">
                        <span class="mb-1 block">Reason (when required)</span>
                        <input name="reason" placeholder="Reason for waitlist / reject / incomplete..." class="jca-input min-w-[14rem] text-ink" />
                    </label>
                    <button
                        type="submit"
                        class="inline-flex min-h-[2.75rem] items-center gap-2 rounded-lg bg-brand-gold-500 px-4 py-2 text-sm font-black text-brand-navy-900 transition-colors hover:bg-yellow-400"
                    >
                        <x-ui.icon name="check" size="size-4" />
                        Apply to selected
                    </button>
                    <button
                        type="button"
                        @click="selected = []"
                        class="inline-flex min-h-[2.75rem] items-center gap-1 rounded-lg px-3 py-2 text-sm font-bold text-white/70 transition-colors hover:text-white"
                    >
                        Clear
                    </button>
                </div>
            </div>
        </form>
        {{-- Soft-copy preview dialog --}}
        <dialog x-ref="softCopyPreview" @cancel="previewUrl = ''" class="m-auto w-[min(70rem,calc(100%-2rem))] overflow-hidden rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/60" aria-labelledby="soft-copy-preview-title">
            <div class="flex h-[min(52rem,calc(100vh-2rem))] flex-col">
                <header class="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-brand-orange-500">Private soft-copy preview</p>
                        <h2 id="soft-copy-preview-title" class="mt-1 text-lg font-black text-brand-navy-900" x-text="previewName"></h2>
                    </div>
                    <button type="button" class="grid size-10 place-items-center rounded-lg text-muted hover:bg-slate-100" @click="$refs.softCopyPreview.close(); previewUrl = ''" aria-label="Close soft-copy preview"><x-ui.icon name="close" /></button>
                </header>
                <iframe :src="previewUrl" class="min-h-0 flex-1 bg-slate-100" title="Attached soft-copy document"></iframe>
                <footer class="border-t border-border px-5 py-3 text-xs text-muted">Preview access is private and recorded in the audit trail. Close the preview when review is complete.</footer>
            </div>
        </dialog>
    </div>
</x-layouts.app>
