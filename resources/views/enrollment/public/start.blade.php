<x-layouts.app title="Complete enrollment application">
    @php
        $fieldValue = fn (string $field, mixed $default = null) => old($field, data_get([
            'lrn' => $enrollment->student->lrn,
            'student_first_name' => $enrollment->student->first_name,
            'student_middle_name' => $enrollment->student->middle_name,
            'student_last_name' => $enrollment->student->last_name,
            'extension_name' => $enrollment->student->extension_name,
            'sex' => $enrollment->student->sex,
            'birth_date' => $enrollment->student->birth_date?->toDateString(),
            'birth_place' => $enrollment->student->birth_place,
            'mother_tongue' => $enrollment->student->mother_tongue,
            'is_4ps_recipient' => $enrollment->student->is_4ps_recipient === null ? '' : (string) (int) $enrollment->student->is_4ps_recipient,
            'four_ps_household_id' => $enrollment->student->four_ps_household_id,
            'is_learner_with_disability' => $enrollment->student->is_learner_with_disability === null ? '' : (string) (int) $enrollment->student->is_learner_with_disability,
            'disability_type' => $enrollment->student->disability_type,
            'current_address' => $enrollment->student->current_address,
            'permanent_address' => $enrollment->student->permanent_address,
            'same_as_current_address' => filled($enrollment->student->current_address) && $enrollment->student->current_address === $enrollment->student->permanent_address ? '1' : '0',
            'father_first_name' => $enrollment->student->father_first_name,
            'father_middle_name' => $enrollment->student->father_middle_name,
            'father_last_name' => $enrollment->student->father_last_name,
            'father_extension_name' => $enrollment->student->father_extension_name,
            'mother_first_name' => $enrollment->student->mother_first_name,
            'mother_middle_name' => $enrollment->student->mother_middle_name,
            'mother_maiden_last_name' => $enrollment->student->mother_maiden_last_name,
            'mother_extension_name' => $enrollment->student->mother_extension_name,
            // Placeholder guardian names (D-047) are never shown as values.
            'guardian_first_name' => $enrollment->guardian?->first_name,
            'guardian_middle_name' => $enrollment->guardian?->middle_name,
            'guardian_last_name' => $enrollment->guardian?->last_name,
            'guardian_extension_name' => $enrollment->guardian?->extension_name,
            'guardian_relationship' => $enrollment->guardian_relationship,
            'guardian_mobile' => $enrollment->guardian->mobile,
            'student_mobile' => $enrollment->student->mobile,
            'guardian_sms_opt_in' => $smsOptIn['guardian'] ? '1' : '0',
            'student_sms_opt_in' => $smsOptIn['student'] ? '1' : '0',
            'guardian_email' => $enrollment->guardian->email,
            'academic_year_id' => $enrollment->academic_year_id,
            'grade_level_id' => $enrollment->grade_level_id,
            'student_category' => $enrollment->student_category,
            'previous_school_name' => $enrollment->previous_school_name,
            'previous_school_address' => $enrollment->previous_school_address,
        ], $field, $default));
    @endphp
    <div
        class="mx-auto max-w-5xl space-y-6"
        x-data="{
            birthDate: @js($fieldValue('birth_date', '')),
            age: '',
            sameAddress: @js((string) $fieldValue('same_as_current_address', '0') === '1'),
            currentAddress: @js((string) $fieldValue('current_address', '')),
            permanentAddress: @js((string) $fieldValue('permanent_address', '')),
            fourPs: @js((string) $fieldValue('is_4ps_recipient', '')),
            hasDisability: @js((string) $fieldValue('is_learner_with_disability', '')),
            category: @js($fieldValue('student_category', 'regular')),
            showLrn: false,
            calculateAge() {
                if (!this.birthDate) { this.age = ''; return; }
                const birth = new Date(`${this.birthDate}T00:00:00`);
                const today = new Date();
                let value = today.getFullYear() - birth.getFullYear();
                const month = today.getMonth() - birth.getMonth();
                if (month < 0 || (month === 0 && today.getDate() < birth.getDate())) value--;
                this.age = value >= 0 ? value : '';
            }
        }"
        x-init="calculateAge()"
    >
        <header>
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">Registrar-issued access</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-brand-navy-900 sm:text-4xl">Complete the enrollment application</h1>
            <p class="mt-3 max-w-3xl text-base leading-7 text-muted">
                Complete the learner profile using legal records. This private draft is available only through the account issued in person by the Registrar.
            </p>
        </header>

        @if ($errors->any())
            <section class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert" aria-labelledby="application-errors-title">
                <h2 id="application-errors-title" class="font-black text-brand-red-600">Please correct the highlighted information</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-brand-red-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form method="POST" action="{{ route('enrollment.public.update', $enrollment) }}" class="space-y-6">
            @csrf @method('PUT')

            <section class="jca-card p-5 sm:p-7" aria-labelledby="learner-identity-title">
                <div class="border-b border-border pb-4">
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Section 1</p>
                    <h2 id="learner-identity-title" class="mt-1 text-xl font-black text-brand-navy-900">Learner identity</h2>
                    <p class="mt-1 text-sm text-muted"><span class="font-bold text-brand-red-600">*</span> Required field. Enter names exactly as shown on the birth certificate or official learner record.</p>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field for="lrn" label="LRN (if available)" hint="Exactly 12 digits. Leave blank if the learner has not been issued an LRN.">
                        <div class="relative">
                            <input
                                id="lrn"
                                name="lrn"
                                type="password"
                                :type="showLrn ? 'text' : 'password'"
                                value="{{ $fieldValue('lrn') }}"
                                inputmode="numeric"
                                pattern="[0-9]{12}"
                                minlength="12"
                                maxlength="12"
                                autocomplete="off"
                                class="jca-input pr-12"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-lg text-brand-navy-700 transition hover:bg-brand-navy-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-navy-700"
                                @click="showLrn = !showLrn"
                                :aria-pressed="showLrn.toString()"
                                :aria-label="showLrn ? 'Hide LRN' : 'Show LRN'"
                                :title="showLrn ? 'Hide LRN' : 'Show LRN'"
                                aria-controls="lrn"
                            >
                                <span x-show="!showLrn"><x-ui.icon name="eye" size="size-5" /></span>
                                <span x-show="showLrn" x-cloak><x-ui.icon name="eye-off" size="size-5" /></span>
                            </button>
                        </div>
                    </x-ui.field>
                    <x-ui.field for="student_first_name" label="First name *">
                        <input id="student_first_name" name="student_first_name" value="{{ $fieldValue('student_first_name') }}" required readonly maxlength="100" class="jca-input bg-slate-50">
                    </x-ui.field>
                    <x-ui.field for="student_middle_name" label="Middle name">
                        <input id="student_middle_name" name="student_middle_name" value="{{ $fieldValue('student_middle_name') }}" readonly maxlength="100" class="jca-input bg-slate-50">
                    </x-ui.field>
                    <x-ui.field for="student_last_name" label="Last name *">
                        <input id="student_last_name" name="student_last_name" value="{{ $fieldValue('student_last_name') }}" required readonly maxlength="100" class="jca-input bg-slate-50">
                    </x-ui.field>
                    <x-ui.field for="extension_name" label="Extension name" hint="Examples: Jr., Sr., II, III">
                        <input id="extension_name" name="extension_name" value="{{ $fieldValue('extension_name') }}" readonly maxlength="30" class="jca-input bg-slate-50">
                    </x-ui.field>
                    <x-ui.field for="sex" label="Sex *">
                        <select id="sex" name="sex" required class="jca-input">
                            <option value="">Select</option>
                            @foreach ($sexes as $value => $label)
                                <option value="{{ $value }}" @selected($fieldValue('sex') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field for="birth_date" label="Birth date *">
                        <input type="date" id="birth_date" name="birth_date" required readonly max="{{ now()->subDay()->toDateString() }}" value="{{ $fieldValue('birth_date') }}" class="jca-input bg-slate-50" x-model="birthDate" @change="calculateAge()">
                    </x-ui.field>
                    <x-ui.field for="calculated_age" label="Current age" hint="Calculated automatically from the birth date; it is not stored separately.">
                        <input id="calculated_age" type="text" readonly :value="age" value="" class="jca-input bg-slate-50 text-muted" tabindex="-1">
                    </x-ui.field>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <x-ui.field for="birth_place" label="Place of birth (municipality/city) *">
                            <input id="birth_place" name="birth_place" value="{{ $fieldValue('birth_place') }}" required maxlength="255" class="jca-input">
                        </x-ui.field>
                    </div>
                </div>
            </section>

            <section class="jca-card p-5 sm:p-7" aria-labelledby="background-title">
                <div class="border-b border-border pb-4">
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Section 2</p>
                    <h2 id="background-title" class="mt-1 text-xl font-black text-brand-navy-900">Learner background and indicators</h2>
                    <p class="mt-1 text-sm text-muted">Sensitive information is collected only for authorized school records and applicable DepEd reporting.</p>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.field for="mother_tongue" label="Mother tongue *">
                        <input id="mother_tongue" name="mother_tongue" value="{{ $fieldValue('mother_tongue') }}" required maxlength="100" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="religion" label="Religion (Christianity only)">
                        <input id="religion" name="religion" value="Christianity" readonly aria-readonly="true" class="jca-input bg-slate-50 text-muted">
                    </x-ui.field>
                    {{-- An application never saved yet (no mother tongue) leaves the question unanswered. --}}
                    <x-people.indigenous-peoples-question :saved-group="$enrollment->student->ip_group" :answered="filled($enrollment->student->mother_tongue)" class="sm:col-span-2" />

                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-1 text-sm font-bold text-ink">Is the learner's family a 4Ps beneficiary? *</legend>
                        <div class="mt-3 flex flex-wrap gap-5">
                            <label class="inline-flex items-center gap-2"><input type="radio" name="is_4ps_recipient" value="1" required @checked($fieldValue('is_4ps_recipient') === '1') x-model="fourPs"> <span>Yes</span></label>
                            <label class="inline-flex items-center gap-2"><input type="radio" name="is_4ps_recipient" value="0" required @checked($fieldValue('is_4ps_recipient') === '0') x-model="fourPs"> <span>No</span></label>
                        </div>
                        @error('is_4ps_recipient')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                        <div class="mt-4" x-show="fourPs === '1'">
                            <x-ui.field for="four_ps_household_id" label="4Ps household ID *">
                                <input id="four_ps_household_id" name="four_ps_household_id" value="{{ $fieldValue('four_ps_household_id') }}" maxlength="50" class="jca-input" :required="fourPs === '1'">
                            </x-ui.field>
                        </div>
                    </fieldset>

                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-1 text-sm font-bold text-ink">Is the learner a learner with disability? *</legend>
                        <div class="mt-3 flex flex-wrap gap-5">
                            <label class="inline-flex items-center gap-2"><input type="radio" name="is_learner_with_disability" value="1" required @checked($fieldValue('is_learner_with_disability') === '1') x-model="hasDisability"> <span>Yes</span></label>
                            <label class="inline-flex items-center gap-2"><input type="radio" name="is_learner_with_disability" value="0" required @checked($fieldValue('is_learner_with_disability') === '0') x-model="hasDisability"> <span>No</span></label>
                        </div>
                        @error('is_learner_with_disability')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                        <div class="mt-4" x-show="hasDisability === '1'">
                            <x-ui.field for="disability_type" label="Type of disability *">
                                <select id="disability_type" name="disability_type" class="jca-input" :required="hasDisability === '1'">
                                    <option value="">Select</option>
                                    @foreach ($disabilityTypes as $value => $label)
                                        <option value="{{ $value }}" @selected($fieldValue('disability_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                        </div>
                    </fieldset>
                </div>
            </section>

            <section class="jca-card p-5 sm:p-7" aria-labelledby="address-title">
                <div class="border-b border-border pb-4">
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Section 3</p>
                    <h2 id="address-title" class="mt-1 text-xl font-black text-brand-navy-900">Learner address</h2>
                    <p class="mt-1 text-sm text-muted">Include house number, street/sitio/purok, barangay, municipality/city, province, and ZIP code.</p>
                </div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.field for="current_address" label="Current address *">
                        <textarea id="current_address" name="current_address" rows="4" required maxlength="1000" class="jca-input" x-model="currentAddress" @input="if (sameAddress) permanentAddress = currentAddress">{{ $fieldValue('current_address') }}</textarea>
                    </x-ui.field>
                    <div>
                        <x-ui.field for="permanent_address" label="Permanent address *">
                            <textarea id="permanent_address" name="permanent_address" rows="4" maxlength="1000" class="jca-input read-only:bg-slate-100 read-only:text-muted" x-model="permanentAddress" :readonly="sameAddress" :required="!sameAddress">{{ $fieldValue('permanent_address') }}</textarea>
                        </x-ui.field>
                        <input type="hidden" name="same_as_current_address" value="0">
                        <label class="mt-3 inline-flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" name="same_as_current_address" value="1" @checked($fieldValue('same_as_current_address') === '1') x-model="sameAddress" @change="if (sameAddress) permanentAddress = currentAddress" class="mt-0.5">
                            <span>Permanent address is the same as the current address</span>
                        </label>
                    </div>
                    {{-- D-052: optional; lets the school text the learner when they agree. --}}
                    <div>
                        <x-ui.field for="guardian_mobile" label="Guardian mobile number" hint="Use 09XXXXXXXXX or +639XXXXXXXXX. Required if no email is given.">
                            <input type="tel" id="student_mobile" name="student_mobile" value="{{ $fieldValue('student_mobile') }}" maxlength="30" inputmode="tel" class="jca-input">
                        </x-ui.field>
                        <input type="hidden" name="student_sms_opt_in" value="0">
                        <label class="mt-3 inline-flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" name="student_sms_opt_in" value="1" @checked($fieldValue('student_sms_opt_in') === '1') class="mt-0.5">
                            <span>Text school announcements to the learner's number</span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="jca-card p-5 sm:p-7" aria-labelledby="family-title">
                <div class="border-b border-border pb-4">
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Section 4</p>
                    <h2 id="family-title" class="mt-1 text-xl font-black text-brand-navy-900">Parent and guardian information</h2>
                    <p class="mt-1 text-sm text-muted">Use full legal names. Parent-name fields may be left blank when not applicable; the guardian responsible for this application, their relationship to the learner, and at least one contact (mobile or email) are required.</p>
                    @php($guardianPending = \App\Modules\Enrollment\Http\Requests\CompleteEnrollmentApplicationRequest::guardianDetailsPending($enrollment))
                    @unless ($guardianPending)
                        <p class="mt-2 rounded-lg bg-canvas p-3 text-xs text-muted">The guardian's name and relationship were recorded by the Registrar in person and can only be corrected at the Registrar's office.</p>
                    @endunless
                </div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field for="father_first_name" label="Father's first name">
                        <input id="father_first_name" name="father_first_name" value="{{ $fieldValue('father_first_name') }}" maxlength="100" autocomplete="off" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="father_middle_name" label="Father's middle name">
                        <input id="father_middle_name" name="father_middle_name" value="{{ $fieldValue('father_middle_name') }}" maxlength="100" autocomplete="off" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="father_last_name" label="Father's last name">
                        <input id="father_last_name" name="father_last_name" value="{{ $fieldValue('father_last_name') }}" maxlength="100" autocomplete="off" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="father_extension_name" label="Father's suffix">
                        <input id="father_extension_name" name="father_extension_name" value="{{ $fieldValue('father_extension_name') }}" maxlength="30" placeholder="Jr., Sr., III" autocomplete="off" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="mother_first_name" label="Mother's first name">
                        <input id="mother_first_name" name="mother_first_name" value="{{ $fieldValue('mother_first_name') }}" maxlength="100" autocomplete="off" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="mother_middle_name" label="Mother's middle name">
                        <input id="mother_middle_name" name="mother_middle_name" value="{{ $fieldValue('mother_middle_name') }}" maxlength="100" autocomplete="off" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="mother_maiden_last_name" label="Mother's maiden last name">
                        <input id="mother_maiden_last_name" name="mother_maiden_last_name" value="{{ $fieldValue('mother_maiden_last_name') }}" maxlength="100" autocomplete="off" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="mother_extension_name" label="Mother's suffix">
                        <input id="mother_extension_name" name="mother_extension_name" value="{{ $fieldValue('mother_extension_name') }}" maxlength="30" autocomplete="off" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="guardian_first_name" label="Guardian first name *">
                        <input id="guardian_first_name" name="guardian_first_name" value="{{ $fieldValue('guardian_first_name') }}" required maxlength="100" autocomplete="given-name" class="jca-input {{ $guardianPending ? '' : 'bg-slate-50' }}" @readonly(! $guardianPending)>
                    </x-ui.field>
                    <x-ui.field for="guardian_middle_name" label="Guardian middle name">
                        <input id="guardian_middle_name" name="guardian_middle_name" value="{{ $fieldValue('guardian_middle_name') }}" maxlength="100" autocomplete="additional-name" class="jca-input {{ $guardianPending ? '' : 'bg-slate-50' }}" @readonly(! $guardianPending)>
                    </x-ui.field>
                    <x-ui.field for="guardian_last_name" label="Guardian last name *">
                        <input id="guardian_last_name" name="guardian_last_name" value="{{ $fieldValue('guardian_last_name') }}" required maxlength="100" autocomplete="family-name" class="jca-input {{ $guardianPending ? '' : 'bg-slate-50' }}" @readonly(! $guardianPending)>
                    </x-ui.field>
                    <x-ui.field for="guardian_extension_name" label="Guardian suffix">
                        <input id="guardian_extension_name" name="guardian_extension_name" value="{{ $fieldValue('guardian_extension_name') }}" maxlength="30" autocomplete="honorific-suffix" class="jca-input {{ $guardianPending ? '' : 'bg-slate-50' }}" @readonly(! $guardianPending)>
                    </x-ui.field>
                    <x-ui.field for="guardian_relationship" label="Relationship to learner *">
                        <select id="guardian_relationship" name="guardian_relationship" required class="jca-input {{ $guardianPending ? '' : 'bg-slate-50' }}">
                            <option value="">Select</option>
                            @foreach ($guardianPending ? $guardianRelationships : collect($guardianRelationships)->only([$enrollment->guardian_relationship])->all() as $value => $label)
                                <option value="{{ $value }}" @selected($fieldValue('guardian_relationship') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <div>
                        <x-ui.field for="guardian_mobile" label="Guardian mobile number" hint="Use 09XXXXXXXXX or +639XXXXXXXXX. Required if no email is given.">
                            <input type="tel" id="guardian_mobile" name="guardian_mobile" value="{{ $fieldValue('guardian_mobile') }}" maxlength="30" inputmode="tel" autocomplete="tel" class="jca-input">
                        </x-ui.field>
                        <input type="hidden" name="guardian_sms_opt_in" value="0">
                        <label class="mt-3 inline-flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" name="guardian_sms_opt_in" value="1" @checked($fieldValue('guardian_sms_opt_in') === '1') class="mt-0.5">
                            <span>Text school announcements to this number</span>
                        </label>
                    </div>
                   <x-ui.field for="guardian_email" label="Guardian email address (optional)" hint="Optional. No verification code is sent.">
                        <input type="email" id="guardian_email" name="guardian_email" value="{{ $fieldValue('guardian_email') }}" maxlength="255" autocomplete="email" class="jca-input">
                    </x-ui.field>
                </div>
            </section>

            <section class="jca-card p-5 sm:p-7" aria-labelledby="enrollment-title">
                @php($studentSelfApplicant = auth()->user()?->hasRole('student') ?? false)
                <div class="border-b border-border pb-4">
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Section 5</p>
                    <h2 id="enrollment-title" class="mt-1 text-xl font-black text-brand-navy-900">Enrollment information</h2>
                </div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.field for="academic_year_id" label="School year *">
                        <select id="academic_year_id" name="academic_year_id" required class="jca-input">
                            <option value="">Select</option>
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" @selected($fieldValue('academic_year_id') == $year->id)>{{ $year->name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field for="grade_level_id" label="Grade level to enroll *">
                        <select id="grade_level_id" name="grade_level_id" required class="jca-input">
                            <option value="">Select</option>
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}" @selected($fieldValue('grade_level_id') == $grade->id)>{{ $grade->name }}</option>
                            @endforeach
                        </select>
                        @if($studentSelfApplicant)
                            <p class="mt-2 text-xs leading-5 text-muted">Student accounts may apply for Junior High School Grades 7€€“10. Pre-elementary and Grades 1€€“6 require a parent or guardian.</p>
                        @else
                            <p class="mt-2 text-xs leading-5 text-muted">This guardian-assisted form offers pre-elementary and Grades 1€€“6. Grade 7€€“10 learners must use an invited Student login.</p>
                        @endif
                    </x-ui.field>
                    <div class="sm:col-span-2">
                        <x-ui.field for="student_category" label="Application type *">
                            <select id="student_category" name="student_category" required class="jca-input" x-model="category">
                                <option value="regular" @selected($fieldValue('student_category', 'regular') === 'regular')>Regular</option>
                                <option value="transferee" @selected($fieldValue('student_category') === 'transferee')>Transferee</option>
                                <option value="support_review" @selected($fieldValue('student_category') === 'support_review')>Requires support review</option>
                            </select>
                        </x-ui.field>
                    </div>
                    <div class="sm:col-span-2 grid gap-5 sm:grid-cols-2" x-show="category === 'transferee'">
                        <x-ui.field for="previous_school_name" label="Previous school name *">
                            <input id="previous_school_name" name="previous_school_name" value="{{ $fieldValue('previous_school_name') }}" maxlength="255" class="jca-input" :required="category === 'transferee'">
                        </x-ui.field>
                        <x-ui.field for="previous_school_address" label="Previous school address *">
                            <input id="previous_school_address" name="previous_school_address" value="{{ $fieldValue('previous_school_address') }}" maxlength="255" class="jca-input" :required="category === 'transferee'">
                        </x-ui.field>
                    </div>
                </div>
            </section>

            <section class="jca-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                <div>
                    <h2 class="font-black text-brand-navy-900">Save the authenticated application</h2>
                    <p class="mt-1 max-w-2xl text-sm text-muted">Review the information before continuing. No SMS verification or secret resume link is required.</p>
                </div>
                <x-ui.button type="submit" class="shrink-0">Save draft application</x-ui.button>
            </section>
        </form>
    </div>
</x-layouts.app>
