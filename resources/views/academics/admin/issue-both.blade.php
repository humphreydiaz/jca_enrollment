
<x-layouts.app title="Issue Student and Parent Accounts">
    <div class="mx-auto max-w-5xl space-y-6 p-4">

        <div class="space-y-2">
            <a href="{{ route('people.admin.index') }}"
               class="text-sm underline">
                ← Back to Family Links
            </a>

            <h1 class="text-2xl font-bold">
                Issue Student & Parent Accounts
            </h1>

            <p class="text-sm text-gray-500">
                Grade 7–10 Account Issuance
            </p>
        </div>

        @if (session('issued_both'))
            @php
                $access = session('issued_both');
            @endphp

            <div class="rounded-xl border p-6 space-y-4">
                <h2 class="text-xl font-bold">
                    Accounts Successfully Issued
                </h2>

                <div class="space-y-2">
                    <h3 class="font-semibold">Student Account</h3>

                    <p>
                        Name:
                        {{ $access['student_name'] }}
                    </p>

                    <p>
                        Student ID:
                        <strong>
                            {{ $access['student_number'] }}
                        </strong>
                    </p>

                    <p>
                        Temporary Password:
                        <strong>
                            {{ $access['temporary_password'] }}
                        </strong>
                    </p>
                </div>

                <hr>

                <div class="space-y-2">
                    <h3 class="font-semibold">
                        Parent/Guardian Account
                    </h3>

                    <p>
                        Name:
                        {{ $access['parent_name'] }}
                    </p>

                    <p>
                        Account Status:
                        {{ $access['parent_status'] }}
                    </p>

                    @if (!empty($access['parent_login']) && !empty($access['parent_temporary_password']))
                        <p>
                            Parent ID (sign-in ID):
                            <strong class="font-mono">{{ $access['parent_login'] }}</strong>
                        </p>

                        <p>
                            Temporary Password:
                            <strong class="font-mono">{{ $access['parent_temporary_password'] }}</strong>
                        </p>

                        @if (!empty($access['parent_expires_at']))
                            <p>Valid until: {{ $access['parent_expires_at'] }}</p>
                        @endif
                    @endif
                </div>

                <hr>

                <p class="font-semibold">
                    Family Link:
                    {{ $access['verified'] ? 'Verified' : 'Pending Verification' }}
                </p>

                <p class="text-sm">
                    Give the Student and Parent credentials
                    separately to their verified owners.
                    Do not leave this page unattended.
                </p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-400 p-4">
                <h2 class="font-semibold">
                    Please correct the following:
                </h2>

                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li class="text-red-600">
                            {{ $error }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('people.family.issue-both.store') }}"
            method="POST"
            class="space-y-6 rounded-xl border p-6"
        >
            @csrf

            <div class="space-y-4">
                <h2 class="text-lg font-bold">
                    1. Enrollment Information
                </h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-1">
                        <span>School Year *</span>

                        <select name="academic_year_id"
                                class="w-full rounded-lg border p-3"
                                required>
                            <option value="">
                                Select School Year
                            </option>

                            @foreach ($years as $year)
                                <option value="{{ $year->id }}"
                                    @selected(old('academic_year_id') == $year->id)>
                                    {{ $year->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span>Grade Level *</span>

                        <select name="grade_level_id"
                                class="w-full rounded-lg border p-3"
                                required>
                            <option value="">
                                Select Grade Level
                            </option>

                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}"
                                    @selected(old('grade_level_id') == $grade->id)>
                                    {{ $grade->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span>Student Category *</span>

                        <select name="student_category"
                                class="w-full rounded-lg border p-3"
                                required>
                            <option value="regular"
                                @selected(old('student_category', 'regular') === 'regular')>
                                Regular
                            </option>

                            <option value="transferee"
                                @selected(old('student_category') === 'transferee')>
                                Transferee
                            </option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span>LRN (Optional)</span>

                        <input type="text"
                               name="lrn"
                               maxlength="12"
                               inputmode="numeric"
                               value="{{ old('lrn') }}"
                               class="w-full rounded-lg border p-3">
                    </label>
                </div>
            </div>

            <hr>

            <div class="space-y-4">
                <h2 class="text-lg font-bold">
                    2. Student Information
                </h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-1">
                        <span>First Name *</span>
                        <input type="text"
                               name="student_first_name"
                               value="{{ old('student_first_name') }}"
                               class="w-full rounded-lg border p-3"
                               required>
                    </label>

                    <label class="space-y-1">
                        <span>Middle Name</span>
                        <input type="text"
                               name="student_middle_name"
                               value="{{ old('student_middle_name') }}"
                               class="w-full rounded-lg border p-3">
                    </label>

                    <label class="space-y-1">
                        <span>Last Name *</span>
                        <input type="text"
                               name="student_last_name"
                               value="{{ old('student_last_name') }}"
                               class="w-full rounded-lg border p-3"
                               required>
                    </label>

                    <label class="space-y-1">
                        <span>Extension Name</span>
                        <input type="text"
                               name="extension_name"
                               value="{{ old('extension_name') }}"
                               class="w-full rounded-lg border p-3">
                    </label>

                    <label class="space-y-1">
                        <span>Sex *</span>
                        <select name="sex"
                                class="w-full rounded-lg border p-3"
                                required>
                            <option value="">Select Sex</option>
                            <option value="male"
                                @selected(old('sex') === 'male')>
                                Male
                            </option>
                            <option value="female"
                                @selected(old('sex') === 'female')>
                                Female
                            </option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span>Date of Birth *</span>
                        <input type="date"
                               name="birth_date"
                               value="{{ old('birth_date') }}"
                               class="w-full rounded-lg border p-3"
                               required>
                    </label>
                </div>
            </div>

            <hr>

            <div class="space-y-4">
                <h2 class="text-lg font-bold">
                    3. Parent/Guardian Information
                </h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-1">
                        <span>First Name *</span>
                        <input type="text"
                               name="guardian_first_name"
                               value="{{ old('guardian_first_name') }}"
                               class="w-full rounded-lg border p-3"
                               required>
                    </label>

                    <label class="space-y-1">
                        <span>Middle Name</span>
                        <input type="text"
                               name="guardian_middle_name"
                               value="{{ old('guardian_middle_name') }}"
                               class="w-full rounded-lg border p-3">
                    </label>

                    <label class="space-y-1">
                        <span>Last Name *</span>
                        <input type="text"
                               name="guardian_last_name"
                               value="{{ old('guardian_last_name') }}"
                               class="w-full rounded-lg border p-3"
                               required>
                    </label>

                    <label class="space-y-1">
                        <span>Extension Name</span>
                        <input type="text"
                               name="guardian_extension_name"
                               value="{{ old('guardian_extension_name') }}"
                               class="w-full rounded-lg border p-3">
                    </label>

                    <label class="space-y-1">
                        <span>Relationship *</span>

                        <select name="guardian_relationship"
                                class="w-full rounded-lg border p-3"
                                required>
                            <option value="">
                                Select Relationship
                            </option>

                            @foreach ($relationships as $value => $label)
                                <option value="{{ $value }}"
                                    @selected(old('guardian_relationship') == $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span>Mobile Number *</span>

                        <input type="tel"
                               name="guardian_mobile"
                               placeholder="09XXXXXXXXX"
                               value="{{ old('guardian_mobile') }}"
                               class="w-full rounded-lg border p-3"
                               required>
                    </label>

                    <label class="space-y-1">
                        <span>Email Address (Optional)</span>

                        <input type="email"
                               name="guardian_email"
                               value="{{ old('guardian_email') }}"
                               class="w-full rounded-lg border p-3">
                    </label>
                </div>
            </div>

            <hr>

            <div class="space-y-4">
                <h2 class="text-lg font-bold">
                    4. Identity Verification
                </h2>

                <label class="flex items-start gap-3">
                    <input type="checkbox"
                           name="identity_confirmed"
                           value="1"
                           class="mt-1"
                           required>

                    <span>
                        I confirm that the Registrar has verified
                        the identities of both the Student and
                        Parent/Guardian in person.
                    </span>
                </label>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white"
                >
                    Verify & Create Both Accounts
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
