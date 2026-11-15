
<x-layouts.public title="Application status">

    @php
        // Enrollment closing date validation
        $closingDate = $enrollment->academicYear->enrollment_closes_on;

        $isEnrollmentClosed = ! $enrollment->academicYear->enrollment_is_open || $enrollment->academicYear->status !== 'open' || (filled($closingDate)
            && now('Asia/Manila')->toDateString()
                > \Illuminate\Support\Carbon::parse($closingDate)->toDateString());
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">

        {{-- Application information --}}
        <section class="jca-card p-6 sm:p-8"
                 aria-labelledby="application-title">

            <p class="text-sm font-bold uppercase tracking-[0.16em] text-brand-orange-500">
                Saved application
            </p>

            <h1 id="application-title"
                class="mt-2 text-3xl font-black text-brand-navy-900">
                {{ $enrollment->student->full_name }}
            </h1>

            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">

                <div>
                    <dt class="font-semibold text-muted">Private reference</dt>
                    <dd class="font-bold text-ink">
                        {{ $enrollment->application_number }}
                    </dd>
                </div>

                <div>
                    <dt class="font-semibold text-muted">Status</dt>
                    <dd class="mt-1">
                        <x-ui.status-badge :status="$enrollment->status" />
                    </dd>
                </div>

                <div>
                    <dt class="font-semibold text-muted">School year</dt>
                    <dd>{{ $enrollment->academicYear->name }}</dd>
                </div>

                <div>
                    <dt class="font-semibold text-muted">Requested grade</dt>
                    <dd>{{ $enrollment->gradeLevel->name }}</dd>
                </div>

            </dl>

            @if($resumeToken !== '')

                <p class="mt-5 text-sm text-muted">
                    Save this page securely. The resume link contains
                    a private secret and must not be shared.
                </p>

                @if(filled($enrollment->student->student_number))

                    <div class="mt-4 rounded-xl border border-brand-blue-100 bg-brand-blue-50 p-4 sm:p-5">

                        <p class="text-sm leading-6 text-brand-navy-900">
                            The learner's permanent
                            <strong>student number</strong> is

                            <span class="font-black tracking-wide">
                                {{ $enrollment->student->student_number }}
                            </span>.

                            The student signs in with this number
                            and their password once their account is set up.
                        </p>

                    </div>

                @endif

            @else

                <div class="mt-5 rounded-xl border border-brand-blue-100 bg-brand-blue-50 p-4 sm:p-5">

                    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">
                        Signing in
                    </p>

                    @if(filled($enrollment->student->student_number))

                        <p class="mt-2 text-sm leading-6 text-brand-navy-900">
                            Your student number is

                            <span class="font-black tracking-wide">
                                {{ $enrollment->student->student_number }}
                            </span>.

                            Use your <strong>student number and password</strong>
                            to sign in anytime — to check your enrollment
                            progress now, and to open your student portal
                            once you are officially enrolled. You will use
                            this same student number for as long as you
                            study here.
                        </p>

                    @else

                        <p class="mt-2 text-sm leading-6 text-brand-navy-900">
                            When you submit this application you will be
                            given a permanent <strong>student number</strong>.
                            From then on, sign in with your
                            <strong>student number and password</strong>
                            to track your enrollment — and to open your
                            student portal once you are officially enrolled.
                        </p>

                    @endif

                    <p class="mt-2 text-xs text-muted">
                        This application is securely linked to your
                        signed-in Student account. Your permanent
                        student profile remains separate until approval
                        and Admin connection.
                    </p>

                </div>

            @endif

        </section>


        {{-- Enrollment closed notification --}}
        @if($isEnrollmentClosed && $enrollment->status === 'draft')

            <section class="rounded-xl border border-red-200 bg-red-50 p-6"
                     role="alert"
                     aria-labelledby="enrollment-closed-title">

                <div class="flex items-center gap-3">

                    <x-ui.icon name="lock" size="size-6" />

                    <h2 id="enrollment-closed-title"
                        class="text-xl font-black text-red-700">
                        Enrollment is Closed
                    </h2>

                </div>

                <p class="mt-4 text-sm leading-6 text-red-700">
                    The enrollment period for
                    <strong>{{ $enrollment->academicYear->name }}</strong>
                    ended on

                    <strong>
                        {{ \Illuminate\Support\Carbon::parse($closingDate)->format('F j, Y') }}
                    </strong>.
                </p>

                <p class="mt-3 text-sm leading-6 text-red-700">
                    Late enrollment applications can no longer be
                    submitted online. Please contact the Registrar
                    for assistance regarding further enrollment
                    procedures.
                </p>

                <div class="mt-4 rounded-lg border border-red-200 bg-white p-3">

                    <p class="text-sm font-semibold text-red-700">
                        Online enrollment submission is currently unavailable.
                    </p>

                </div>

            </section>

        @endif


        {{-- Application timeline --}}
        <section class="jca-card p-6"
                 aria-labelledby="application-timeline-title">

            <h2 id="application-timeline-title"
                class="text-xl font-black text-brand-navy-900">
                Application timeline
            </h2>

            <ol class="mt-4 space-y-3 border-l-2 border-brand-blue-100 pl-4 text-sm">

                <li>
                    <p class="font-bold text-brand-navy-900">
                        Application created
                    </p>

                    <time class="text-muted"
                          datetime="{{ $enrollment->created_at->toIso8601String() }}">
                        {{ $enrollment->created_at->timezone('Asia/Manila')->format('d M Y, g:i A') }}
                    </time>
                </li>

                @foreach($enrollment->history->sortBy('changed_at') as $event)

                    <li>

                        <p class="font-bold text-brand-navy-900">
                            {{ str($event->to_status)->replace('_', ' ')->title() }}
                        </p>

                        <time class="text-muted"
                              datetime="{{ $event->changed_at->toIso8601String() }}">
                            {{ $event->changed_at->timezone('Asia/Manila')->format('d M Y, g:i A') }}
                        </time>

                        @if($event->reason)

                            <p class="mt-1 text-muted">
                                {{ $event->reason }}
                            </p>

                        @endif

                    </li>

                @endforeach

            </ol>

        </section>


        {{-- Contact verification --}}
        @if($enrollment->contact_verified_at === null)

            <section class="jca-card p-6"
                     aria-labelledby="verify-title">

                <h2 id="verify-title"
                    class="text-xl font-black text-brand-navy-900">
                    1. Verify the contact
                </h2>

                @php
                    $verificationChannelPhrase = match (true) {
                        $enrollment->guardian->mobile && $enrollment->guardian->email
                            => 'by SMS to your mobile number and by email',

                        (bool) $enrollment->guardian->mobile
                            => 'by SMS to your mobile number',

                        default
                            => 'to your email contact',
                    };
                @endphp

                <p class="mt-2 text-sm text-muted">
                    Enter the six-digit code we sent
                    {{ $verificationChannelPhrase }}.
                    The code expires in 15 minutes.
                </p>

                <form method="POST"
                      action="{{ route('enrollment.public.verify', $enrollment) }}"
                      class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">

                    @csrf

                    <input type="hidden"
                           name="resume_token"
                           value="{{ $resumeToken }}">

                    <x-ui.field for="verification_code"
                                label="Verification code">

                        <input id="verification_code"
                               name="verification_code"
                               inputmode="numeric"
                               autocomplete="one-time-code"
                               maxlength="6"
                               required
                               class="block w-full rounded-lg border-border px-3 py-3">

                    </x-ui.field>

                    <x-ui.button type="submit">
                        Verify contact
                    </x-ui.button>

                </form>

                <form method="POST"
                      action="{{ route('enrollment.public.resend', $enrollment) }}"
                      class="mt-3">

                    @csrf

                    <input type="hidden"
                           name="resume_token"
                           value="{{ $resumeToken }}">

                    <button type="submit"
                            class="text-sm font-semibold text-brand-navy-700 underline underline-offset-2 hover:text-brand-navy-900">
                        Didn't get it? Resend code
                    </button>

                </form>

            </section>


        {{-- Complete application --}}
        @elseif($enrollment->status === 'draft' && ! $isEnrollmentClosed)

            <section class="jca-card p-6"
                     aria-labelledby="details-title">

                <h2 id="details-title"
                    class="text-xl font-black text-brand-navy-900">
                    2. Complete the application
                </h2>

                <p class="mt-2 text-sm text-muted">
                    Save this step before submitting.
                    Transferees must identify the previous school.
                </p>

                <form method="POST"
                      action="{{ route('enrollment.public.update', $enrollment) }}"
                      class="mt-5 grid gap-4 sm:grid-cols-2">

                    @csrf
                    @method('PUT')

                    <input type="hidden"
                           name="resume_token"
                           value="{{ $resumeToken }}">

                    <x-ui.field for="guardian_relationship"
                                label="Relationship to student">

                        <select id="guardian_relationship"
                                name="guardian_relationship"
                                required
                                class="jca-input">

                            <option value="">Select</option>

                            @foreach($guardianRelationships as $value => $label)

                                <option value="{{ $value }}"
                                        @selected(old('guardian_relationship', $enrollment->guardian_relationship) === $value)>
                                    {{ $label }}
                                </option>

                            @endforeach

                        </select>

                    </x-ui.field>

                    <x-ui.field for="student_middle_name"
                                label="Student middle name (optional)">

                        <input id="student_middle_name"
                               name="student_middle_name"
                               value="{{ old('student_middle_name', $enrollment->student->middle_name) }}"
                               class="block w-full rounded-lg border-border px-3 py-3">

                    </x-ui.field>

                    @if($enrollment->student_category === 'transferee')

                        <x-ui.field for="previous_school_name"
                                    label="Previous school">

                            <input id="previous_school_name"
                                   name="previous_school_name"
                                   required
                                   value="{{ old('previous_school_name', $enrollment->previous_school_name) }}"
                                   class="block w-full rounded-lg border-border px-3 py-3">

                        </x-ui.field>

                        <x-ui.field for="previous_school_address"
                                    label="Previous school address">

                            <input id="previous_school_address"
                                   name="previous_school_address"
                                   required
                                   value="{{ old('previous_school_address', $enrollment->previous_school_address) }}"
                                   class="block w-full rounded-lg border-border px-3 py-3">

                        </x-ui.field>

                    @endif

                    <div class="sm:col-span-2">

                        <x-ui.button type="submit" variant="secondary">
                            Save application details
                        </x-ui.button>

                    </div>

                </form>


                {{-- Submit application with deadline validation --}}
                @if($isEnrollmentClosed)

                    <div class="mt-6 border-t border-border pt-5">

                        <p class="mb-3 text-sm font-semibold text-red-700">
                            The enrollment deadline has passed.
                            New submissions are no longer accepted.
                        </p>

                        <button type="button"
                                disabled
                                aria-disabled="true"
                                class="cursor-not-allowed rounded-lg bg-gray-300 px-5 py-3 text-sm font-bold text-gray-600">
                            Submit Application — Closed
                        </button>

                    </div>

                @else

                    <form method="POST"
                          action="{{ route('enrollment.public.submit', $enrollment) }}"
                          class="mt-6 border-t border-border pt-5">

                        @csrf

                        <input type="hidden"
                               name="resume_token"
                               value="{{ $resumeToken }}">

                        <p class="mb-3 text-sm text-muted">
                            Submission locks the public draft for
                            Registrar review. Confirm that the
                            information above is complete.
                        </p>

                        <x-ui.button type="submit">
                            Submit application
                        </x-ui.button>

                    </form>

                @endif

            </section>


        {{-- Application already submitted --}}
        @else

            <section class="jca-card p-6">

                <h2 class="text-xl font-black text-brand-navy-900">
                    Application received
                </h2>

                <p class="mt-2 text-sm text-muted">
                    JCA will review identity, duplicates, and
                    the applicable requirement checklist.
                    An account invitation is issued only
                    after staff verification.
                </p>

            </section>

        @endif


        {{-- Enrollment requirements --}}
        @if($enrollment->requirements->isNotEmpty())

            <section class="jca-card p-6"
                     aria-labelledby="requirements-title">

                <h2 id="requirements-title"
                    class="text-xl font-black text-brand-navy-900">
                    Requirements
                </h2>

                <p class="mt-2 text-sm text-muted">
                    A verified soft copy or a received hard copy
                    may support enrollment approval. The physical
                    hard copy remains required and may be submitted later.
                </p>

                <div class="mt-4 space-y-3">

                    @foreach($enrollment->requirements as $requirement)

                        <x-enrollment.requirement-status
                            :requirement="$requirement" />

                    @endforeach

                </div>

            </section>

        @elseif($enrollment->status === 'draft' && ! $isEnrollmentClosed)

            <section class="jca-card p-6"
                     aria-labelledby="requirements-title">

                <h2 id="requirements-title"
                    class="text-xl font-black text-brand-navy-900">
                    Requirements
                </h2>

                <p class="mt-2 text-sm text-muted">
                    The applicable Birth Certificate and school-record
                    checklist, with its private upload controls,
                    appears after you verify the contact, complete
                    the draft, and select
                    <strong>Submit application</strong>.
                </p>

            </section>

        @endif

    </div>

</x-layouts.public>
