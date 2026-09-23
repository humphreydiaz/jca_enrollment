@php
    $birthDate = $student->birth_date;
    $identity = [
        'Full name' => $student->full_name,
        'Preferred name' => $student->preferred_name,
        'Sex' => $student->sex ? ucfirst($student->sex) : null,
        'Date of birth' => $birthDate?->format('d M Y'),
        'Age' => $birthDate ? $birthDate->age.' years old' : null,
        'Place of birth' => $student->birth_place,
        'LRN' => $student->lrn,
        'Student number' => $student->student_number,
    ];
    $background = [
        'Mother tongue' => $student->mother_tongue,
        'Religion' => $student->religion,
        'Indigenous Peoples community' => $student->ip_group,
        '4Ps recipient' => $student->is_4ps_recipient ? 'Yes' : 'No',
        '4Ps household ID' => $student->is_4ps_recipient ? $student->four_ps_household_id : null,
        'Learner with disability' => $student->is_learner_with_disability ? 'Yes' : 'No',
        'Type of disability' => $student->is_learner_with_disability ? $student->disability_type : null,
    ];
    $household = [
        "Father's name" => $student->father_name,
        "Mother's maiden name" => $student->mother_maiden_name,
        'Current address' => $student->current_address,
        'Permanent address' => $student->permanent_address,
    ];
@endphp

<div class="space-y-6">

    {{-- ── Record header ── --}}
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-lg font-black text-brand-navy-700">
                {{ strtoupper(substr($student->first_name ?? 'S', 0, 1)) }}{{ strtoupper(substr($student->last_name ?? '', 0, 1)) }}
            </span>
            <div class="min-w-0">
                <h3 class="text-xl font-black text-brand-navy-900">{{ $student->full_name }}</h3>
                <p class="mt-0.5 text-sm text-muted">
                    {{ $student->student_number ?: 'No student number' }}
                    @if($student->lrn) · LRN {{ $student->lrn }} @endif
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.status-badge :status="$student->status" />
            @if($student->user)
                <x-ui.status-badge status="active" label="Portal login connected" />
            @else
                <x-ui.status-badge status="pending" label="No portal login" />
            @endif
        </div>
    </header>

    {{-- ── Profile detail groups ── --}}
    <div class="grid gap-4 lg:grid-cols-3">
        @foreach([
            ['title' => 'Learner identity', 'icon' => 'users', 'fields' => $identity],
            ['title' => 'Background and support', 'icon' => 'support', 'fields' => $background],
            ['title' => 'Household and address', 'icon' => 'people', 'fields' => $household],
        ] as $group)
            <section class="rounded-xl border border-border bg-white p-4">
                <h4 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-brand-navy-900">
                    <x-ui.icon :name="$group['icon']" size="size-4" />
                    {{ $group['title'] }}
                </h4>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach($group['fields'] as $label => $value)
                        <div class="grid grid-cols-[minmax(0,10rem)_1fr] gap-2">
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="font-semibold text-ink">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>

    {{-- ── Linked guardians ── --}}
    <section class="rounded-xl border border-border bg-white p-4">
        <h4 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-brand-navy-900">
            <x-ui.icon name="people" size="size-4" />
            Guardians on record
        </h4>

        @forelse($student->guardians as $guardian)
            <div class="mt-3 flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border bg-canvas/40 p-3 text-sm">
                <div class="min-w-0">
                    <p class="font-bold text-brand-navy-900">{{ $guardian->full_name }}</p>
                    <p class="mt-0.5 text-muted">
                        {{ $guardian->pivot->relationship_type ? str($guardian->pivot->relationship_type)->headline() : 'Relationship not recorded' }}
                        · {{ $guardian->mobile ?: 'No mobile' }}
                        @if($guardian->email) · {{ $guardian->email }} @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @if($guardian->pivot->is_primary_contact)
                        <x-ui.status-badge status="active" label="Primary contact" />
                    @endif
                    <x-ui.status-badge :status="$guardian->pivot->verified_at ? 'verified' : 'not_verified'" />
                    @if($guardian->pivot->may_manage_enrollment)
                        <x-ui.status-badge status="granted" label="May manage enrollment" />
                    @endif
                    @if($guardian->pivot->may_view_financials)
                        <x-ui.status-badge status="granted" label="May view financials" />
                    @endif
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-muted">No guardian is linked to this learner profile yet.</p>
        @endforelse
    </section>

    {{-- ── Enrollment history ── --}}
    <section class="rounded-xl border border-border bg-white p-4">
        <h4 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-brand-navy-900">
            <x-ui.icon name="enrollment" size="size-4" />
            Enrollment history
        </h4>

        @forelse($enrollments as $enrollment)
            @php
                $reqTotal = $enrollment->requirements->count();
                $reqCleared = $enrollment->requirements->filter(fn ($requirement) =>
                    in_array($requirement->status, ['verified', 'waived'], true)
                    || $requirement->soft_copy_status === 'verified'
                    || ($requirement->status === 'submitted' && $requirement->received_at !== null)
                )->count();
            @endphp
            <article class="mt-3 rounded-lg border border-border p-3 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-bold text-brand-navy-900">
                            {{ $enrollment->gradeLevel?->name ?? 'Grade not set' }}
                            <span class="font-semibold text-muted">· {{ $enrollment->academicYear?->name ?? 'Year not set' }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-muted">{{ $enrollment->application_number }}</p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <x-ui.status-badge :status="$enrollment->status" />
                        <x-ui.status-badge :status="$enrollment->student_category" tone="info" variant="outline" />
                    </div>
                </div>

                <dl class="mt-3 grid gap-x-4 gap-y-1 text-xs sm:grid-cols-2 xl:grid-cols-3">
                    @foreach([
                        'Applicant guardian' => $enrollment->guardian?->full_name,
                        'Stated relationship' => $enrollment->guardian_relationship ? str($enrollment->guardian_relationship)->headline() : null,
                        'Previous school' => $enrollment->previous_school_name,
                        'Previous school address' => $enrollment->previous_school_address,
                        'Submitted' => $enrollment->submitted_at?->format('d M Y'),
                        'Approved' => $enrollment->approved_at?->format('d M Y'),
                        'Enrolled' => $enrollment->enrolled_at?->format('d M Y'),
                        'Requirements cleared' => $reqTotal ? $reqCleared.' of '.$reqTotal : 'None recorded',
                    ] as $label => $value)
                        <div class="flex gap-1.5">
                            <dt class="shrink-0 text-muted">{{ $label }}:</dt>
                            <dd class="font-semibold text-ink">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>
        @empty
            <p class="mt-3 text-sm text-muted">This learner profile has no enrollment application on record.</p>
        @endforelse
    </section>
</div>
