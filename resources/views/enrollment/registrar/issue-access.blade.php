<x-layouts.app title="Issue walk-in access">
    @if (session('issued_access'))
        @php($slip = session('issued_access'))
        <x-slot:printSlip>
    <x-print.access-slip :slip="$slip" />

    @if(session('issued_both') && !empty(session('issued_both')['parent_temporary_password']))
        @php($both = session('issued_both'))

        <div style="max-width:700px;margin:20px auto;padding:18px;border:1px solid #aaa;font-family:Arial,sans-serif;color:#111;">
            <h2 style="font-size:18px;margin-bottom:12px;">
                Parent/Guardian Account Credentials
            </h2>

            <p>
                <strong>Application Number:</strong>
                {{ $slip['application_number'] ?? 'N/A' }}
            </p>

            <p>
                <strong>Parent/Guardian Name:</strong>
                {{ $both['parent_name'] ?? 'N/A' }}
            </p>

            <p>
                <strong>Parent ID (sign-in ID):</strong>
                {{ $both['parent_login'] ?? 'N/A' }}
            </p>

            <p>
                <strong>Temporary Password:</strong>
                {{ $both['parent_temporary_password'] }}
            </p>

            <p>
                <strong>Expires:</strong>
                {{ $both['parent_expires_at'] ?? 'N/A' }}
            </p>

            <p style="font-size:12px;margin-top:15px;">
                Change your temporary password after signing in.
            </p>
        </div>
    @endif
</x-slot:printSlip>
    @endif
    <div class="mx-auto max-w-5xl space-y-6">
        <x-ui.page-header
            title="Issue walk-in enrollment access"
            description="Use this only after the parent, guardian, or Junior High School learner appears in person and the Registrar checks their identity and possible duplicate records."
            eyebrow="Registrar workspace"
        />

        @if(session('issued_access'))
            @php($access = session('issued_access'))
            <section class="rounded-2xl border-2 border-success-700 bg-emerald-50 p-5 sm:p-7" aria-labelledby="issued-access-title">
                <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-success-700">Display once — give credentials only to the verified account owner</p>
                <h2 id="issued-access-title" class="mt-2 text-2xl font-black text-brand-navy-900">Temporary access issued</h2>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-sm font-semibold text-muted">Account owner</dt><dd class="font-black text-ink">{{ $access['account_owner_name'] }} ({{ $access['account_owner_type'] }})</dd></div>
                    <div><dt class="text-sm font-semibold text-muted">Application reference</dt><dd class="font-black text-ink">{{ $access['application_number'] }}</dd></div>
                    <div><dt class="text-sm font-semibold text-muted">Student ID</dt><dd class="select-all font-mono text-xl font-black tracking-wide text-brand-navy-900">{{ $access['student_number'] }}</dd></div>
                    <div><dt class="text-sm font-semibold text-muted">Temporary password</dt><dd class="select-all font-mono text-xl font-black tracking-wide text-brand-navy-900">{{ $access['temporary_password'] }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-sm font-semibold text-muted">Expires</dt><dd class="font-bold text-ink">{{ $access['expires_at'] }}</dd></div>
                </dl>
                <p class="mt-5 text-sm leading-6 text-ink">The owner signs in at <strong>{{ route('identity.login') }}</strong> with the Student ID, replaces this password, then completes the enrollment application. The password is stored only as a secure hash and is not sent by SMS or email.</p>
                <button type="button" class="jca-button jca-button-secondary mt-4 print:hidden" onclick="window.print()">Print Credentials</button>
            </section>
        @endif

        @if(session('issued_both'))
            @php($both = session('issued_both'))
            @if(!empty($both['parent_login']) && !empty($both['parent_temporary_password']))
            <section class="rounded-2xl border-2 border-success-700 bg-emerald-50 p-5 sm:p-7" id="parent-access-print-area" aria-labelledby="parent-issued-title">
                <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-success-700">Display once — give credentials only to the verified parent/guardian</p>
                <h2 id="parent-issued-title" class="mt-2 text-2xl font-black text-brand-navy-900">Parent/Guardian temporary access issued</h2>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
<div>
    <dt>Application Number</dt>
    <dd>{{ session('issued_access')['application_number'] ?? 'N/A' }}</dd>
</div>
                    <div>
                        <dt class="text-sm font-semibold text-muted">Account owner</dt>
                        <dd class="font-black text-ink">{{ $both['parent_name'] }} (Parent/Guardian)</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-muted">Family relationship</dt>
                        <dd class="font-bold text-ink">{{ $both['verified'] ? 'Verified and automatically linked' : 'Pending verification' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-muted">Parent ID (sign-in ID)</dt>
                        <dd class="select-all font-mono text-xl font-black tracking-wide text-brand-navy-900">{{ $both['parent_login'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-muted">Temporary password</dt>
                        <dd class="select-all font-mono text-xl font-black tracking-wide text-brand-navy-900">{{ $both['parent_temporary_password'] }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-semibold text-muted">Expires</dt>
                        <dd class="font-bold text-ink">{{ $both['parent_expires_at'] }}</dd>
                    </div>
                </dl>
                <p class="mt-5 text-sm leading-6 text-ink">Parent/Guardian has a separate account and temporary password. The family relationship is automatically linked during issuance. Give this access information only to the verified parent/guardian. The parent signs in at {{ route('identity.login') }} with the Parent ID above, not the Student ID.</p>
                
            </section>
            @else
            <section class="rounded-xl border border-border bg-canvas p-5" aria-label="Previous parent account issuance">
                <h2 class="font-bold text-brand-navy-900">Parent/Guardian account</h2>
                <p class="mt-2 text-sm text-muted">This is a previous issuance record. No Parent temporary password was included in this session. Do not use or guess credentials.</p>
                <p class="mt-1 text-sm text-muted">{{ $both['parent_name'] ?? 'Parent/Guardian' }} — {{ $both['parent_status'] ?? 'Account status unavailable' }}</p>
                @if(!empty($both['parent_setup_url']))
                    <p class="mt-2 text-sm text-muted">Existing activation link: <span class="break-all select-all">{{ $both['parent_setup_url'] }}</span></p>
                @endif
            </section>
            @endif
        @endif

        @if($errors->any())
            <section class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
                <h2 class="font-black text-brand-red-600">Access was not issued</h2>
                <p class="mt-1 text-sm text-brand-red-600">{{ $errors->first() }}</p>
            </section>
        @endif

        <form method="POST" action="{{ route('enrollment.registrar.issue-access.store') }}" class="space-y-6" @input="fieldVersion++" @change="fieldVersion++"
              x-data="{ grade: @js((string) old('grade_level_id', '')), year: @js((string) old('academic_year_id', '')),
              category: @js(old('student_category', 'regular')),
              requirementOptions: @js($requirementOptions),
              receivedIds: @js(array_map('strval', old('received_document_types', []))),
              fieldVersion: 0,
              get applicableRequirements() {
                  return this.requirementOptions[this.year]?.[this.category] || []
              },
              get informationComplete() {
                  this.fieldVersion;
                  const fields = Array.from(
                      this.$el.querySelectorAll(
                          'input[required], select[required], textarea[required]'
                      )
                  );
                  return !!this.year && !!this.grade &&
                      fields
                          .filter(el =>
                              !el.disabled &&
                              el.name !== 'identity_confirmed' &&
                              el.getClientRects().length > 0
                          )
                          .every(el => el.value && el.checkValidity());
              },
              guardianGrades: @js($grades->filter(fn ($grade) => ! $grade->allowsStudentAccountByDefault())->pluck('id')->map(fn ($id) => (string) $id)->values()), juniorGrades: @js($grades->filter(fn ($grade) => in_array($grade->code, ['G7', 'G8', 'G9', 'G10'], true))->pluck('id')->map(fn ($id) => (string) $id)->values()), get guardianOwned() { return this.guardianGrades.includes(this.grade) }, get juniorHigh() { return this.juniorGrades.includes(this.grade) }, get needsGuardian() { return this.guardianOwned || this.juniorHigh } }">
            @csrf
            <section class="jca-card p-5 sm:p-7">
                <h2 class="text-xl font-black text-brand-navy-900">Learner and enrollment</h2>
                <p class="mt-1 text-sm text-muted">Nursery–Grade 6: Parent-owned account. Grade 7–10: Student and Parent/Guardian accounts are issued together and linked automatically.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field for="academic_year_id" label="School year *"><select id="academic_year_id" name="academic_year_id" required class="jca-input" x-model="year" @change="receivedIds = []"><option value="">Select</option>@foreach($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field for="grade_level_id" label="Grade level *"><select id="grade_level_id" name="grade_level_id" required class="jca-input" x-model="grade"><option value="">Select</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected(old('grade_level_id') == $grade->id)>{{ $grade->name }} — {{ $grade->allowsStudentAccountByDefault() ? 'Student-owned' : 'Guardian-owned' }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field for="student_category" label="Application type *"><select id="student_category" name="student_category" required class="jca-input" x-model="category" @change="receivedIds = []"><option value="regular" @selected(old('student_category', 'regular') === 'regular')>Regular</option><option value="transferee" @selected(old('student_category') === 'transferee')>Transferee</option><option value="support_review" @selected(old('student_category') === 'support_review')>Requires support review</option></select></x-ui.field>
                   
<x-ui.field for="lrn" label="LRN (if available)">
    <div x-data="{ showLrn: false }" class="relative">
        <input
            id="lrn"
            name="lrn"
            type="password"
            :type="showLrn ? 'text' : 'password'"
            value="{{ old('lrn') }}"
            inputmode="numeric"
            pattern="[0-9]{12}"
            minlength="12"
            maxlength="12"
            autocomplete="off"
            class="jca-input pr-12"
        >

        <button
            type="button"
            @click="showLrn = !showLrn"
            :aria-label="showLrn ? 'Hide LRN' : 'Show LRN'"
            :aria-pressed="showLrn.toString()"
            class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-brand-navy-700"
        >
            <span x-show="!showLrn">
                <x-ui.icon name="eye" size="size-5" />
            </span>
            <span x-show="showLrn" x-cloak>
                <x-ui.icon name="eye-off" size="size-5" />
            </span>
        </button>
    </div>
</x-ui.field>

                    <x-ui.field for="student_first_name" label="Learner first name *"><input id="student_first_name" name="student_first_name" value="{{ old('student_first_name') }}" required maxlength="100" class="jca-input"></x-ui.field>
                    <x-ui.field for="student_middle_name" label="Learner middle name"><input id="student_middle_name" name="student_middle_name" value="{{ old('student_middle_name') }}" maxlength="100" class="jca-input"></x-ui.field>
                    <x-ui.field for="student_last_name" label="Learner last name *"><input id="student_last_name" name="student_last_name" value="{{ old('student_last_name') }}" required maxlength="100" class="jca-input"></x-ui.field>
                    <x-ui.field for="extension_name" label="Learner suffix"><input id="extension_name" name="extension_name" value="{{ old('extension_name') }}" maxlength="30" class="jca-input"></x-ui.field>
                    <x-ui.field for="sex" label="Sex *"><select id="sex" name="sex" required class="jca-input"><option value="">Select</option>@foreach($sexes as $value => $label)<option value="{{ $value }}" @selected(old('sex') === $value)>{{ $label }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field for="birth_date" label="Birth date *"><input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date') }}" max="{{ now()->subDay()->toDateString() }}" required class="jca-input"></x-ui.field>
                </div>
            </section>


            {{-- Parent details for both Nursery-Grade 6 and Grade 7-10. --}}
            <section class="jca-card p-5 sm:p-7" x-show="needsGuardian" x-cloak x-transition.opacity aria-labelledby="walk-in-guardian-title">
                <h2 id="walk-in-guardian-title" class="text-xl font-black text-brand-navy-900">Parent/Guardian Information</h2>
                <p class="mt-1 text-sm text-muted">Enter the details of the verified parent or guardian.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field for="guardian_first_name" label="First name *">
                        <input id="guardian_first_name" name="guardian_first_name" value="{{ old('guardian_first_name') }}" maxlength="100" autocomplete="off" class="jca-input" :required="needsGuardian" :disabled="!needsGuardian">
                    </x-ui.field>

                    <x-ui.field for="guardian_middle_name" label="Middle name">
                        <input id="guardian_middle_name" name="guardian_middle_name" value="{{ old('guardian_middle_name') }}" maxlength="100" autocomplete="off" class="jca-input" :disabled="!needsGuardian">
                    </x-ui.field>

                    <x-ui.field for="guardian_last_name" label="Last name *">
                        <input id="guardian_last_name" name="guardian_last_name" value="{{ old('guardian_last_name') }}" maxlength="100" autocomplete="off" class="jca-input" :required="needsGuardian" :disabled="!needsGuardian">
                    </x-ui.field>

                    <x-ui.field for="guardian_extension_name" label="Suffix">
                        <input id="guardian_extension_name" name="guardian_extension_name" value="{{ old('guardian_extension_name') }}" maxlength="30" class="jca-input" :disabled="!needsGuardian">
                    </x-ui.field>

                    <x-ui.field for="guardian_relationship" label="Relationship *">
                        <select id="guardian_relationship" name="guardian_relationship" class="jca-input" :required="needsGuardian" :disabled="!needsGuardian">
                            <option value="">Select relationship</option>
                            @foreach($guardianRelationships as $value => $label)
                                <option value="{{ $value }}" @selected(old('guardian_relationship') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field for="guardian_mobile" label="Mobile number">
                        <input type="tel" id="guardian_mobile" name="guardian_mobile" value="{{ old('guardian_mobile') }}" maxlength="30" inputmode="tel" placeholder="09XXXXXXXXX" class="jca-input" :required="juniorHigh" :disabled="!needsGuardian">
                        <p class="mt-1 text-xs text-muted" x-show="juniorHigh" x-cloak>Required for Grade 7–10</p>
                        <p class="mt-1 text-xs text-muted" x-show="guardianOwned" x-cloak>Optional for Nursery–Grade 6</p>
                    </x-ui.field>

                    <x-ui.field for="guardian_email" label="Email address">
                        <input type="email" id="guardian_email" name="guardian_email" value="{{ old('guardian_email') }}" maxlength="255" class="jca-input" :disabled="!needsGuardian">
                    </x-ui.field>
                </div>

                <p class="mt-4 text-xs text-muted" x-show="juniorHigh" x-cloak>* Required for Grade 7–10. A separate Parent/Guardian account will be linked automatically after verification.</p>
                <p class="mt-4 text-xs text-muted" x-show="guardianOwned" x-cloak>* Required name and relationship fields. Contact details are optional.</p>
            </section>

            {{-- HARD-COPY REQUIREMENTS CHECKLIST --}}
            <section
                class="jca-card p-5 sm:p-7"
                x-show="informationComplete"
                x-cloak
            >
                <h2 class="text-xl font-black text-brand-navy-900">
                    Hard-Copy Requirements Checklist
                </h2>

                <p class="mt-2 text-sm text-muted">
                    Check only the hard-copy documents physically
                    received by the Registrar.
                    Unchecked requirements remain pending.
                    No online upload is required.
                </p>

                <div class="mt-5 space-y-3">
                    <template
                        x-for="document in applicableRequirements"
                        :key="document.id"
                    >
                        <label class="jca-check flex items-center gap-3 text-sm">
                            <input
                                type="checkbox"
                                name="received_document_types[]"
                                :value="String(document.id)"
                                x-model="receivedIds"
                            >

                            <span>
                                <span x-text="document.name"></span>
                                <span
                                    x-show="document.required"
                                    class="text-muted"
                                >
                                    (Required)
                                </span>
                            </span>
                        </label>
                    </template>
                </div>

                <p
                    class="mt-3 text-sm text-brand-red-600"
                    x-show="applicableRequirements.length === 0"
                >
                    No active requirements are configured
                    for this school year and application type.
                </p>

                @error('received_document_types')
                    <p class="mt-2 text-sm text-brand-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </section>
            <section class="jca-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                <div class="space-y-3">
                    <h2 class="font-black text-brand-navy-900">Confirm the in-person review</h2>
                    <p class="max-w-2xl text-sm text-muted" x-show="!juniorHigh">For Nursery–Grade 6, issue the existing parent-owned temporary access and draft enrollment application.</p>
                    <p class="max-w-2xl text-sm text-muted" x-show="juniorHigh" x-cloak>For Grade 7–10, verify both people. One submission issues separate Student and Parent accounts and automatically links the Parent/Guardian to the learner.</p>
                    <label class="jca-check text-sm" x-show="juniorHigh" x-cloak>
                        <input type="checkbox" name="identity_confirmed" value="1" :required="juniorHigh" :disabled="!juniorHigh" @checked(old('identity_confirmed'))>
                        I verified the identities of both the learner and their parent/guardian in person.
                    </label>
                </div>
                <button type="submit" class="jca-button jca-button-primary shrink-0" x-text="juniorHigh ? 'Verify & Create Both Accounts' : 'Issue temporary access'"></button>
            </section>
        </form>
    </div>

    <script>
        function printParentAccessSlip() {
            const slip = document.getElementById('parent-access-print-area');
            if (!slip) return;

            const printable = slip.cloneNode(true);
            printable.querySelectorAll('button').forEach(button => button.remove());

            const printWindow = window.open('', '_blank', 'width=850,height=700');
            if (!printWindow) {
                alert('Please allow pop-ups for this website to print the Parent/Guardian slip.');
                return;
            }

            printWindow.document.open();
            printWindow.document.write(`<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Parent/Guardian Temporary Access Slip</title>
    <style>
        @page { size: A4 portrait; margin: 16mm; }
        body { font-family: Arial, sans-serif; color: #172b4d; margin: 0; padding: 25px; }
        section { border: 2px solid #137b52; border-radius: 12px; padding: 24px; }
        h2 { margin: 8px 0 22px; font-size: 23px; }
        p { font-size: 12px; line-height: 1.65; }
        section > p:first-child { color: #137b52; font-weight: bold; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; }
        dl { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 24px; margin: 18px 0; }
        dt { font-size: 12px; color: #64748b; font-weight: bold; margin-bottom: 5px; }
        dd { margin: 0; font-size: 15px; color: #172b4d; font-weight: bold; overflow-wrap: anywhere; }
        dd.select-all { font-size: 19px; font-family: monospace; }
        dl > div:last-child { grid-column: 1 / -1; }
        button { display: none !important; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>${printable.outerHTML}</body>
</html>`);
            printWindow.document.close();
            printWindow.addEventListener('load', () => {
                printWindow.focus();
                printWindow.print();
            });
        }
    </script>

</x-layouts.app>
