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
        "Father's name" => $student->father_full_name,
        "Mother's maiden name" => $student->mother_full_name,
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

    <section class="rounded-xl border border-border bg-white p-4">
        <details @if($errors->any()) open @endif>
            <summary class="cursor-pointer font-black text-brand-navy-900">Correct learner information</summary>
            <p class="mt-2 text-sm leading-6 text-muted">Registrar corrections are limited to demographic, address, and parent-name fields. Religion and academic, section, requirement, finance, support, and grading history are protected.</p>
            @error('profile')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
            <form method="POST" action="{{ route('people.registrar.students.update', $student) }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                @method('PUT')
                @foreach([
                    'first_name' => ['First name', 100],
                    'middle_name' => ['Middle name', 100],
                    'last_name' => ['Last name', 100],
                    'extension_name' => ['Suffix', 30],
                    'preferred_name' => ['Preferred name', 100],
                    'birth_place' => ['Place of birth', 255],
                    'mother_tongue' => ['Mother tongue', 100],
                ] as $field => [$label, $max])
                    <x-ui.field :for="$field" :label="$label">
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $student->{$field}) }}" maxlength="{{ $max }}" class="jca-input">
                    </x-ui.field>
                @endforeach
                <x-ui.field for="sex" label="Sex">
                    <select id="sex" name="sex" class="jca-input">
                        <option value="">Not recorded</option>
                        @foreach(\App\Modules\People\Support\LearnerProfileOptions::SEXES as $value => $label)
                            <option value="{{ $value }}" @selected(old('sex', $student->sex) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field for="birth_date" label="Date of birth">
                    <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date', $student->birth_date?->toDateString()) }}" max="{{ today()->subDay()->toDateString() }}" class="jca-input">
                </x-ui.field>
                @foreach([
                    'father_first_name' => "Father's first name",
                    'father_middle_name' => "Father's middle name",
                    'father_last_name' => "Father's last name",
                    'father_extension_name' => "Father's suffix",
                    'mother_first_name' => "Mother's first name",
                    'mother_middle_name' => "Mother's middle name",
                    'mother_maiden_last_name' => "Mother's maiden last name",
                    'mother_extension_name' => "Mother's suffix",
                ] as $field => $label)
                    <x-ui.field :for="$field" :label="$label">
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $student->{$field}) }}" maxlength="{{ str_contains($field, 'extension') ? 30 : 100 }}" class="jca-input">
                    </x-ui.field>
                @endforeach
                <x-ui.field for="current_address" label="Current address" class="sm:col-span-2">
                    <textarea id="current_address" name="current_address" rows="3" maxlength="1000" class="jca-input">{{ old('current_address', $student->current_address) }}</textarea>
                </x-ui.field>
                <x-ui.field for="permanent_address" label="Permanent address" class="sm:col-span-2">
                    <textarea id="permanent_address" name="permanent_address" rows="3" maxlength="1000" class="jca-input">{{ old('permanent_address', $student->permanent_address) }}</textarea>
                </x-ui.field>
                <x-people.indigenous-peoples-question :saved-group="$student->ip_group" class="sm:col-span-2 lg:col-span-4" />
                <fieldset class="rounded-lg border border-border p-3 sm:col-span-2">
                    <legend class="px-1 text-sm font-bold">Learner indicators</legend>
                    <input type="hidden" name="is_4ps_recipient" value="0">
                    <label class="jca-check"><input type="checkbox" name="is_4ps_recipient" value="1" @checked(old('is_4ps_recipient', $student->is_4ps_recipient))> 4Ps recipient</label>
                    <label class="mt-3 block text-sm font-bold" for="four_ps_household_id">4Ps household ID</label>
                    <input id="four_ps_household_id" name="four_ps_household_id" value="{{ old('four_ps_household_id', $student->four_ps_household_id) }}" maxlength="50" class="jca-input">
                </fieldset>
                <fieldset class="rounded-lg border border-border p-3 sm:col-span-2">
                    <legend class="px-1 text-sm font-bold">Disability information</legend>
                    <input type="hidden" name="is_learner_with_disability" value="0">
                    <label class="jca-check"><input type="checkbox" name="is_learner_with_disability" value="1" @checked(old('is_learner_with_disability', $student->is_learner_with_disability))> Learner with disability</label>
                    <label class="mt-3 block text-sm font-bold" for="disability_type">Type</label>
                    <select id="disability_type" name="disability_type" class="jca-input">
                        <option value="">Select when applicable</option>
                        @foreach(\App\Modules\People\Support\LearnerProfileOptions::DISABILITY_TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('disability_type', $student->disability_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </fieldset>
                <x-ui.field for="reason" label="Reason for correction *" hint="Describe the verified source or mistake being corrected." class="sm:col-span-2 lg:col-span-4">
                    <textarea id="reason" name="reason" required minlength="10" maxlength="2000" rows="3" class="jca-input">{{ old('reason') }}</textarea>
                </x-ui.field>
                <div class="sm:col-span-2 lg:col-span-4"><x-ui.button type="submit">Save audited correction</x-ui.button></div>
            </form>
        </details>
    </section>

    <section class="rounded-xl border border-amber-200 bg-amber-50/40 p-4">
        <details>
            <summary class="cursor-pointer font-black text-brand-navy-900">Correct Student ID or LRN</summary>
            <p class="mt-2 text-sm text-muted">This separate workflow enforces the approved ID format and database uniqueness.</p>
            @error('identifiers')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
            <form method="POST" action="{{ route('people.registrar.students.identifiers', $student) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-ui.field for="student_number" label="Student ID *" hint="Format: JCA-YY-NNN">
                    <input id="student_number" name="student_number" value="{{ old('student_number', $student->student_number) }}" required maxlength="10" pattern="JCA-[0-9]{2}-[0-9]{3}" class="jca-input font-mono uppercase">
                </x-ui.field>
                <x-ui.field for="lrn" label="LRN" hint="12 digits, when assigned by DepEd">
                    <input id="lrn" name="lrn" value="{{ old('lrn', $student->lrn) }}" maxlength="12" inputmode="numeric" pattern="[0-9]{12}" class="jca-input font-mono">
                </x-ui.field>
                <x-ui.field for="identifier_reason" label="Reason for identifier correction *" class="sm:col-span-2">
                    <textarea id="identifier_reason" name="reason" required minlength="10" maxlength="2000" rows="3" class="jca-input"></textarea>
                </x-ui.field>
                <div class="sm:col-span-2"><x-ui.button type="submit" variant="secondary">Save identifier correction</x-ui.button></div>
            </form>
        </details>
    </section>

    @if($student->corrections->isNotEmpty())
        <section class="rounded-xl border border-border bg-white p-4">
            <h4 class="text-xs font-bold uppercase tracking-wider text-brand-navy-900">Correction history</h4>
            <div class="mt-3 space-y-3">
                @foreach($student->corrections->sortByDesc('created_at')->take(20) as $correction)
                    <article class="rounded-lg border border-border p-3 text-sm">
                        <p class="font-bold text-ink">{{ str($correction->field_name)->replace('_', ' ')->headline() }}</p>
                        <p class="mt-1 break-words text-muted"><span class="line-through">{{ filled($correction->before_value) ? $correction->before_value : '—' }}</span> → <span class="font-semibold text-ink">{{ filled($correction->after_value) ? $correction->after_value : '—' }}</span></p>
                        <p class="mt-1 text-xs text-muted">{{ $correction->reason }} · {{ $correction->actor?->name ?? 'Former user' }} · {{ $correction->created_at?->format('d M Y, g:i A') }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── Change history (D-059): who changed this learner's records, and when ── --}}
    @php
        $changeHistory ??= collect();
    @endphp
    <section class="rounded-xl border border-border bg-white p-4" aria-labelledby="change-history-{{ $student->public_id }}">
        <details>
            <summary id="change-history-{{ $student->public_id }}" class="cursor-pointer text-xs font-bold uppercase tracking-wider text-brand-navy-900">
                Change history <span class="font-semibold normal-case tracking-normal text-muted">({{ $changeHistory->count() >= 50 ? 'latest 50' : $changeHistory->count().' recorded' }})</span>
            </summary>
            <p class="mt-2 text-sm text-muted">Every saved change to this learner, their applications, requirements, section placement, and parent/guardian details. Contact numbers and emails are masked.</p>
            @forelse($changeHistory as $entry)
                <article class="mt-3 rounded-lg border border-border p-3 text-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-bold text-ink">{{ \App\Modules\Shared\Audit\AuditChangeSummary::actionLabel($entry->action) }}</p>
                        <p class="text-xs text-muted"><time datetime="{{ $entry->occurred_at->toIso8601String() }}">{{ $entry->occurred_at->timezone(config('app.timezone'))->format('d M Y, g:i A') }}</time> · {{ $entry->actor?->name ?? 'System process' }}</p>
                    </div>
                    <x-audit.change-lines :lines="\App\Modules\Shared\Audit\AuditChangeSummary::lines($entry)" class="mt-2" />
                </article>
            @empty
                <p class="mt-3 text-sm text-muted">No changes have been recorded for this learner yet.</p>
            @endforelse
        </details>
    </section>

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

                <details class="mt-3 border-t border-border pt-3">
                    <summary class="cursor-pointer text-xs font-bold text-brand-navy-700">Correct previous-school facts</summary>
                    <form method="POST" action="{{ route('enrollment.registrar.previous-school.update', $enrollment) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                        @csrf
                        @method('PUT')
                        @error('previous_school')<p class="text-sm font-semibold text-brand-red-600 sm:col-span-2">{{ $message }}</p>@enderror
                        <x-ui.field for="previous_school_name_{{ $enrollment->id }}" label="Previous school name">
                            <input id="previous_school_name_{{ $enrollment->id }}" name="previous_school_name" value="{{ $enrollment->previous_school_name }}" maxlength="255" class="jca-input">
                        </x-ui.field>
                        <x-ui.field for="previous_school_address_{{ $enrollment->id }}" label="Previous school address">
                            <input id="previous_school_address_{{ $enrollment->id }}" name="previous_school_address" value="{{ $enrollment->previous_school_address }}" maxlength="255" class="jca-input">
                        </x-ui.field>
                        <x-ui.field for="previous_school_reason_{{ $enrollment->id }}" label="Reason for correction *" class="sm:col-span-2">
                            <textarea id="previous_school_reason_{{ $enrollment->id }}" name="reason" required minlength="10" maxlength="2000" rows="2" class="jca-input"></textarea>
                        </x-ui.field>
                        <div class="sm:col-span-2"><x-ui.button type="submit" variant="secondary">Save previous-school correction</x-ui.button></div>
                    </form>
                    @if($enrollment->corrections->isNotEmpty())
                        <div class="mt-3 space-y-2 border-t border-border pt-3">
                            @foreach($enrollment->corrections->sortByDesc('created_at') as $correction)
                                <p class="text-xs text-muted"><strong>{{ str($correction->field_name)->replace('_', ' ')->headline() }}:</strong> {{ $correction->before_value ?: '—' }} → {{ $correction->after_value ?: '—' }} · {{ $correction->reason }} · {{ $correction->actor?->name ?? 'Former user' }}</p>
                            @endforeach
                        </div>
                    @endif
                </details>
            </article>
        @empty
            <p class="mt-3 text-sm text-muted">This learner profile has no enrollment application on record.</p>
        @endforelse
    </section>
</div>
