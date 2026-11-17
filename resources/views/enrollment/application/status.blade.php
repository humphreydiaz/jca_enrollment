
<x-layouts.app title="Enrollment application">
    @php
        $schoolYear = $enrollment->academicYear;
        $today = now('Asia/Manila')->toDateString();

        $opensOn = $schoolYear?->enrollment_opens_on
            ? \Illuminate\Support\Carbon::parse($schoolYear->enrollment_opens_on)->toDateString()
            : null;

        $closesOn = $schoolYear?->enrollment_closes_on
            ? \Illuminate\Support\Carbon::parse($schoolYear->enrollment_closes_on)->toDateString()
            : null;

        $enrollmentClosed = ! $schoolYear
            || ($schoolYear->status !== 'open' || ! $schoolYear->enrollment_is_open)
            || ($closesOn && $today > $closesOn);

        $enrollmentNotYetOpen = ! $enrollmentClosed
            && $opensOn
            && $today < $opensOn;

        $canSubmit = ! $enrollmentClosed && ! $enrollmentNotYetOpen;

        $closingDate = $closesOn
            ? \Illuminate\Support\Carbon::parse($closesOn)->format('F j, Y')
            : null;

        $openingDate = $opensOn
            ? \Illuminate\Support\Carbon::parse($opensOn)->format('F j, Y')
            : null;
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">

        @if(session('status'))
            <div role="status" class="rounded-xl border border-green-200 bg-green-50 p-4 text-base font-semibold text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-5 text-red-800">
                <h2 class="text-lg font-black">Unable to process application</h2>

                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="jca-card p-6 sm:p-8" aria-labelledby="application-title">
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-brand-orange-500">
                Authenticated enrollment
            </p>

            <h1 id="application-title" class="mt-2 text-3xl font-black text-brand-navy-900">
                {{ $enrollment->student->full_name }}
            </h1>

            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-semibold text-muted">Application reference</dt>
                    <dd class="font-bold text-ink">{{ $enrollment->application_number }}</dd>
                </div>

                <div>
                    <dt class="font-semibold text-muted">Student ID</dt>
                    <dd class="font-black tracking-wide text-brand-navy-900">
                        {{ $enrollment->student->student_number }}
                    </dd>
                </div>

                <div>
                    <dt class="font-semibold text-muted">Status</dt>
                    <dd class="mt-1">
                        <x-ui.status-badge :status="$enrollment->status" />
                    </dd>
                </div>

                <div>
                    <dt class="font-semibold text-muted">School year and grade</dt>
                    <dd>
                        {{ $schoolYear?->name }} &middot; {{ $enrollment->gradeLevel->name }}
                    </dd>
                </div>
            </dl>

            <div class="mt-5 rounded-xl border border-brand-blue-100 bg-brand-blue-50 p-4 text-sm leading-6 text-brand-navy-900">
                This application was opened after an in-person Registrar review.
                It is linked to your signed-in account; no SMS code or secret
                resume link is used.
            </div>
        </section>

        @if($enrollment->status === 'draft' && ($enrollmentClosed || $enrollmentNotYetOpen))
            <section class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-6 sm:p-8"
                     role="alert"
                     aria-labelledby="enrollment-period-notice">

                <div class="flex items-start gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-extrabold uppercase tracking-wider text-amber-800">
                            Enrollment Notice
                        </p>

                        <h2 id="enrollment-period-notice" class="mt-2 text-2xl font-black text-amber-950">
                            {{ $enrollmentClosed ? 'Enrollment Period Closed' : 'Enrollment Not Yet Open' }}
                        </h2>

                        @if($enrollmentClosed)
                            <p class="mt-3 text-base leading-7 text-amber-950">
                                Online enrollment submissions are currently closed
                                for <strong>{{ $schoolYear?->name ?? 'this school year' }}</strong>.
                            </p>

                            @if($closingDate && $today > $closesOn)
                                <p class="mt-2 text-base leading-7 text-amber-950">
                                    The enrollment period ended on
                                    <strong>{{ $closingDate }}</strong>.
                                </p>
                            @endif
                        @else
                            <p class="mt-3 text-base leading-7 text-amber-950">
                                Online enrollment for
                                <strong>{{ $schoolYear?->name }}</strong>
                                begins on <strong>{{ $openingDate }}</strong>.
                            </p>
                        @endif

                        <div class="mt-5 rounded-xl border border-amber-200 bg-white p-4">
                            <h3 class="text-base font-black text-brand-navy-900">
                                Please Contact the School Registrar
                            </h3>

                            <p class="mt-2 text-sm leading-6 text-brand-navy-900">
                                For enrollment concerns, late enrollment requests,
                                or further assistance, please contact the JCA
                                Registrar directly.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="jca-card p-6" aria-labelledby="application-timeline-title">
            <h2 id="application-timeline-title" class="text-xl font-black text-brand-navy-900">
                Application timeline
            </h2>

            <ol class="mt-4 space-y-3 border-l-2 border-brand-blue-100 pl-4 text-sm">
                <li>
                    <p class="font-bold text-brand-navy-900">Walk-in access issued</p>
                    <time class="text-muted"
                          datetime="{{ $enrollment->access_issued_at?->toIso8601String() }}">
                        {{ $enrollment->access_issued_at?->timezone('Asia/Manila')->format('d M Y, g:i A') }}
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
                            <p class="mt-1 text-muted">{{ $event->reason }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        @if($enrollment->status === 'draft' && ! auth()->user()->can('update', $enrollment))
            <section class="jca-card p-6">
                <h2 class="text-xl font-black text-brand-navy-900">
                    Application being prepared
                </h2>

                <p class="mt-2 text-sm text-muted">
                    The person who received the Registrar's walk-in access,
                    or the Registrar, completes and submits this application.
                </p>
            </section>

        @elseif($enrollment->status === 'draft')
            <section class="jca-card p-6" aria-labelledby="complete-application-title">
                <h2 id="complete-application-title" class="text-xl font-black text-brand-navy-900">
                    Complete and submit the application
                </h2>

                <p class="mt-2 text-sm leading-6 text-muted">
                    Fill in the learner background, address, parent names,
                    and any previous-school information before submitting.
                </p>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    @if($canSubmit)
                        <a class="jca-button jca-button-secondary"
                           href="{{ route('enrollment.public.start', ['enrollment' => $enrollment->public_id]) }}">
                            Complete application details
                        </a>
                    @else
                        <button type="button"
                                disabled
                                class="jca-button jca-button-secondary cursor-not-allowed opacity-50">
                            Complete application details
                        </button>
                    @endif

                    @if($canSubmit)
                        <form method="POST"
                              action="{{ route('enrollment.public.submit', $enrollment) }}">
                            @csrf

                            <x-ui.button type="submit">
                                Submit for Registrar review
                            </x-ui.button>
                        </form>
                    @else
                        <button type="button"
                                disabled
                                class="inline-flex cursor-not-allowed items-center justify-center rounded-lg bg-gray-300 px-5 py-3 text-sm font-bold text-gray-600 opacity-75">
                            Enrollment Submission Unavailable
                        </button>
                    @endif
                </div>

                @if(! $canSubmit)
                    <p class="mt-3 text-sm font-semibold text-amber-800">
                        Submission is unavailable outside the enrollment period.
                        Please contact the Registrar for assistance.
                    </p>
                @endif
            </section>

        @else
            <section class="jca-card p-6">
                <h2 class="text-xl font-black text-brand-navy-900">
                    Application received
                </h2>

                <p class="mt-2 text-sm leading-6 text-muted">
                    JCA will review the submitted information and applicable
                    requirements. Updates appear here after authorized staff action.
                </p>
            </section>
        @endif

        @if($enrollment->requirements->isNotEmpty())
            <section class="jca-card p-6" aria-labelledby="requirements-title">
                <h2 id="requirements-title" class="text-xl font-black text-brand-navy-900">
                    Requirements
                </h2>

                <p class="mt-2 text-sm text-muted">
                    Submit each physical document to the Registrar's office.
                    Enrollment can be approved once every required hard copy
                    is received and verified.
                </p>

                <div class="mt-4 space-y-3">
                    @foreach($enrollment->requirements as $requirement)
                        <x-enrollment.requirement-status :requirement="$requirement" />
                    @endforeach
                </div>
            </section>

        @elseif($enrollment->status === 'draft')
            <section class="jca-card p-6" aria-labelledby="requirements-title">
                <h2 id="requirements-title" class="text-xl font-black text-brand-navy-900">
                    Requirements
                </h2>

                <p class="mt-2 text-sm text-muted">
                    The applicable checklist appears after the completed
                    application is submitted.
                </p>
            </section>
        @endif

    </div>
</x-layouts.app>

