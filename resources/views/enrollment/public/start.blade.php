<x-layouts.public title="Start enrollment">
    <div
        class="mx-auto max-w-5xl space-y-6"
        x-data="{
            birthDate: @js(old('birth_date', '')),
            age: '',
            sameAddress: @js((string) old('same_as_current_address', '0')),
            fourPs: @js((string) old('is_4ps_recipient', '')),
            hasDisability: @js((string) old('is_learner_with_disability', '')),
            category: @js(old('student_category', 'regular')),
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
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">Online enrollment</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-brand-navy-900 sm:text-4xl">Start an enrollment application</h1>
            <p class="mt-3 max-w-3xl text-base leading-7 text-muted">
                Complete the learner profile using the learner's legal records. This creates a private, resumable draft only and does not create a school account.
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

        <form method="POST" action="{{ route('enrollment.public.store') }}" class="space-y-6">
            @csrf

            <section class="jca-card p-5 sm:p-7" aria-labelledby="learner-identity-title">
                <div class="border-b border-border pb-4">
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Section 1</p>
                    <h2 id="learner-identity-title" class="mt-1 text-xl font-black text-brand-navy-900">Learner identity</h2>
                    <p class="mt-1 text-sm text-muted"><span class="font-bold text-brand-red-600">*</span> Required field. Enter names exactly as shown on the birth certificate or official learner record.</p>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field for="lrn" label="LRN (if available)" hint="Exactly 12 digits. Leave blank if the learner has not been issued an LRN.">
                        <input id="lrn" name="lrn" value="{{ old('lrn') }}" inputmode="numeric" pattern="[0-9]{12}" minlength="12" maxlength="12" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="student_first_name" label="First name *">
                        <input id="student_first_name" name="student_first_name" value="{{ old('student_first_name') }}" required maxlength="100" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="student_middle_name" label="Middle name">
                        <input id="student_middle_name" name="student_middle_name" value="{{ old('student_middle_name') }}" maxlength="100" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="student_last_name" label="Last name *">
                        <input id="student_last_name" name="student_last_name" value="{{ old('student_last_name') }}" required maxlength="100" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="extension_name" label="Extension name" hint="Examples: Jr., Sr., II, III">
                        <input id="extension_name" name="extension_name" value="{{ old('extension_name') }}" maxlength="30" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="sex" label="Sex *">
                        <select id="sex" name="sex" required class="jca-input">
                            <option value="">Select</option>
                            @foreach ($sexes as $value => $label)
                                <option value="{{ $value }}" @selected(old('sex') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field for="birth_date" label="Birth date *">
                        <input type="date" id="birth_date" name="birth_date" required max="{{ now()->subDay()->toDateString() }}" value="{{ old('birth_date') }}" class="jca-input" x-model="birthDate" @change="calculateAge()">
                    </x-ui.field>
                    <x-ui.field for="calculated_age" label="Current age" hint="Calculated automatically from the birth date; it is not stored separately.">
                        <input id="calculated_age" type="text" readonly :value="age" value="" class="jca-input bg-slate-50 text-muted" tabindex="-1">
                    </x-ui.field>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <x-ui.field for="birth_place" label="Place of birth (municipality/city) *">
                            <input id="birth_place" name="birth_place" value="{{ old('birth_place') }}" required maxlength="255" class="jca-input">
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
                        <input id="mother_tongue" name="mother_tongue" value="{{ old('mother_tongue') }}" required maxlength="100" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="religion" label="Religion (optional)">
                        <input id="religion" name="religion" value="{{ old('religion') }}" maxlength="100" class="jca-input">
                    </x-ui.field>
                    <div class="sm:col-span-2">
                        <x-ui.field for="ip_group" label="Indigenous Peoples community/group (if applicable)" hint="Leave blank if not applicable.">
                            <input id="ip_group" name="ip_group" value="{{ old('ip_group') }}" maxlength="150" class="jca-input">
                        </x-ui.field>
                    </div>

                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-1 text-sm font-bold text-ink">Is the learner's family a 4Ps beneficiary? *</legend>
                        <div class="mt-3 flex flex-wrap gap-5">
                            <label class="inline-flex items-center gap-2"><input type="radio" name="is_4ps_recipient" value="1" required @checked(old('is_4ps_recipient') === '1') x-model="fourPs"> <span>Yes</span></label>
                            <label class="inline-flex items-center gap-2"><input type="radio" name="is_4ps_recipient" value="0" required @checked(old('is_4ps_recipient') === '0') x-model="fourPs"> <span>No</span></label>
                        </div>
                        @error('is_4ps_recipient')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                        <div class="mt-4" x-show="fourPs === '1'">
                            <x-ui.field for="four_ps_household_id" label="4Ps household ID *">
                                <input id="four_ps_household_id" name="four_ps_household_id" value="{{ old('four_ps_household_id') }}" maxlength="50" class="jca-input" :required="fourPs === '1'">
                            </x-ui.field>
                        </div>
                    </fieldset>

                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-1 text-sm font-bold text-ink">Is the learner a learner with disability? *</legend>
                        <div class="mt-3 flex flex-wrap gap-5">
                            <label class="inline-flex items-center gap-2"><input type="radio" name="is_learner_with_disability" value="1" required @checked(old('is_learner_with_disability') === '1') x-model="hasDisability"> <span>Yes</span></label>
                            <label class="inline-flex items-center gap-2"><input type="radio" name="is_learner_with_disability" value="0" required @checked(old('is_learner_with_disability') === '0') x-model="hasDisability"> <span>No</span></label>
                        </div>
                        @error('is_learner_with_disability')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
                        <div class="mt-4" x-show="hasDisability === '1'">
                            <x-ui.field for="disability_type" label="Type of disability *">
                                <select id="disability_type" name="disability_type" class="jca-input" :required="hasDisability === '1'">
                                    <option value="">Select</option>
                                    @foreach ($disabilityTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('disability_type') === $value)>{{ $label }}</option>
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
                        <textarea id="current_address" name="current_address" rows="4" required maxlength="1000" class="jca-input">{{ old('current_address') }}</textarea>
                    </x-ui.field>
                    <div>
                        <x-ui.field for="permanent_address" label="Permanent address *">
                            <textarea id="permanent_address" name="permanent_address" rows="4" maxlength="1000" class="jca-input disabled:bg-slate-100" :disabled="sameAddress === '1'" :required="sameAddress !== '1'">{{ old('permanent_address') }}</textarea>
                        </x-ui.field>
                        <input type="hidden" name="same_as_current_address" value="0">
                        <label class="mt-3 inline-flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" name="same_as_current_address" value="1" @checked(old('same_as_current_address') === '1') x-model="sameAddress" class="mt-0.5">
                            <span>Permanent address is the same as the current address</span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="jca-card p-5 sm:p-7" aria-labelledby="family-title">
                <div class="border-b border-border pb-4">
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Section 4</p>
                    <h2 id="family-title" class="mt-1 text-xl font-black text-brand-navy-900">Parent and guardian information</h2>
                    <p class="mt-1 text-sm text-muted">Use full legal names. Parent-name fields may be left blank when not applicable; legal guardian information is required.</p>
                </div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.field for="father_name" label="Father's full name" hint="Suggested format: Last name, First name, Middle name">
                        <input id="father_name" name="father_name" value="{{ old('father_name') }}" maxlength="255" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="mother_maiden_name" label="Mother's full maiden name" hint="Suggested format: Last name, First name, Middle name">
                        <input id="mother_maiden_name" name="mother_maiden_name" value="{{ old('mother_maiden_name') }}" maxlength="255" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="guardian_first_name" label="Guardian first name *">
                        <input id="guardian_first_name" name="guardian_first_name" value="{{ old('guardian_first_name') }}" required maxlength="100" autocomplete="given-name" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="guardian_middle_name" label="Guardian middle name">
                        <input id="guardian_middle_name" name="guardian_middle_name" value="{{ old('guardian_middle_name') }}" maxlength="100" autocomplete="additional-name" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="guardian_last_name" label="Guardian last name *">
                        <input id="guardian_last_name" name="guardian_last_name" value="{{ old('guardian_last_name') }}" required maxlength="100" autocomplete="family-name" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="guardian_relationship" label="Relationship to learner *">
                        <select id="guardian_relationship" name="guardian_relationship" required class="jca-input">
                            <option value="">Select</option>
                            @foreach ($guardianRelationships as $value => $label)
                                <option value="{{ $value }}" @selected(old('guardian_relationship') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field for="guardian_mobile" label="Guardian contact number *" hint="Use 09XXXXXXXXX or +639XXXXXXXXX.">
                        <input type="tel" id="guardian_mobile" name="guardian_mobile" value="{{ old('guardian_mobile') }}" required maxlength="30" inputmode="tel" autocomplete="tel" placeholder="09XXXXXXXXX" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="guardian_email" label="Guardian email address (optional)" hint="If supplied, the verification code is sent here. Mobile-only applications require staff-assisted verification while live SMS is disabled.">
                        <input type="email" id="guardian_email" name="guardian_email" value="{{ old('guardian_email') }}" maxlength="255" autocomplete="email" class="jca-input">
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
                                <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field for="grade_level_id" label="Grade level to enroll *">
                        <select id="grade_level_id" name="grade_level_id" required class="jca-input">
                            <option value="">Select</option>
                            @foreach ($grades as $grade)
                                @php($guardianAssistedGrade = ! $grade->allowsStudentAccountByDefault())
                                <option value="{{ $grade->id }}" @selected(old('grade_level_id') == $grade->id) @disabled($studentSelfApplicant && $guardianAssistedGrade)>{{ $grade->name }}{{ $studentSelfApplicant && $guardianAssistedGrade ? ' — guardian-assisted' : '' }}</option>
                            @endforeach
                        </select>
                        @if($studentSelfApplicant)
                            <p class="mt-2 text-xs leading-5 text-muted">Pre-elementary and Grades 1–6 require a parent or guardian to complete the application. Grades 7–12 may apply using their own Student login.</p>
                        @endif
                    </x-ui.field>
                    <div class="sm:col-span-2">
                        <x-ui.field for="student_category" label="Application type *">
                            <select id="student_category" name="student_category" required class="jca-input" x-model="category">
                                <option value="regular" @selected(old('student_category', 'regular') === 'regular')>Regular</option>
                                <option value="transferee" @selected(old('student_category') === 'transferee')>Transferee</option>
                                <option value="support_review" @selected(old('student_category') === 'support_review')>Requires support review</option>
                            </select>
                        </x-ui.field>
                    </div>
                    <div class="sm:col-span-2 grid gap-5 sm:grid-cols-2" x-show="category === 'transferee'">
                        <x-ui.field for="previous_school_name" label="Previous school name *">
                            <input id="previous_school_name" name="previous_school_name" value="{{ old('previous_school_name') }}" maxlength="255" class="jca-input" :required="category === 'transferee'">
                        </x-ui.field>
                        <x-ui.field for="previous_school_address" label="Previous school address *">
                            <input id="previous_school_address" name="previous_school_address" value="{{ old('previous_school_address') }}" maxlength="255" class="jca-input" :required="category === 'transferee'">
                        </x-ui.field>
                    </div>
                </div>
            </section>

            <section class="jca-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                <div>
                    <h2 class="font-black text-brand-navy-900">Save your private draft</h2>
                    <p class="mt-1 max-w-2xl text-sm text-muted">Review the information before continuing. After saving, verify the guardian contact and submit the application for Registrar review.</p>
                </div>
                <x-ui.button type="submit" class="shrink-0">Save draft application</x-ui.button>
            </section>
        </form>
    </div>
</x-layouts.public>
