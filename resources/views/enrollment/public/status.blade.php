<x-layouts.public title="Application status">
    <div class="mx-auto max-w-3xl space-y-6">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="application-title">
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-brand-orange-500">Saved application</p>
            <h1 id="application-title" class="mt-2 text-3xl font-black text-brand-navy-900">{{ $enrollment->student->full_name }}</h1>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold text-muted">Private reference</dt><dd class="font-bold text-ink">{{ $enrollment->application_number }}</dd></div>
                <div><dt class="font-semibold text-muted">Status</dt><dd class="font-bold text-ink">{{ ucfirst(str_replace('_', ' ', $enrollment->status)) }}</dd></div>
                <div><dt class="font-semibold text-muted">School year</dt><dd>{{ $enrollment->academicYear->name }}</dd></div>
                <div><dt class="font-semibold text-muted">Requested grade</dt><dd>{{ $enrollment->gradeLevel->name }}</dd></div>
            </dl>
            <p class="mt-5 text-sm text-muted">Save this page securely. The resume link contains a private secret and must not be shared.</p>
        </section>

        @if($enrollment->contact_verified_at === null)
            <section class="jca-card p-6" aria-labelledby="verify-title">
                <h2 id="verify-title" class="text-xl font-black text-brand-navy-900">1. Verify the contact</h2>
                <p class="mt-2 text-sm text-muted">Enter the six-digit code sent to the email contact. Mobile-only applicants must ask JCA staff to verify the contact after an identity check; live SMS remains disabled.</p>
                @if(session('verification_code'))
                    <p class="mt-3 rounded-lg bg-warning-100 p-3 text-sm text-warning-800"><strong>Local testing code:</strong> {{ session('verification_code') }}</p>
                @endif
                <form method="POST" action="{{ route('enrollment.public.verify', $enrollment) }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <input type="hidden" name="resume_token" value="{{ $resumeToken }}">
                    <x-ui.field for="verification_code" label="Verification code">
                        <input id="verification_code" name="verification_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="block w-full rounded-lg border-border px-3 py-3">
                    </x-ui.field>
                    <x-ui.button type="submit">Verify contact</x-ui.button>
                </form>
            </section>
        @elseif($enrollment->status === 'draft')
            <section class="jca-card p-6" aria-labelledby="details-title">
                <h2 id="details-title" class="text-xl font-black text-brand-navy-900">2. Complete the application</h2>
                <p class="mt-2 text-sm text-muted">Save this step before submitting. Transferees must identify the previous school.</p>
                <form method="POST" action="{{ route('enrollment.public.update', $enrollment) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <input type="hidden" name="resume_token" value="{{ $resumeToken }}">
                    <x-ui.field for="guardian_relationship" label="Relationship to student">
                        <input id="guardian_relationship" name="guardian_relationship" required value="{{ old('guardian_relationship', $enrollment->guardian_relationship) }}" class="block w-full rounded-lg border-border px-3 py-3">
                    </x-ui.field>
                    <x-ui.field for="student_middle_name" label="Student middle name (optional)">
                        <input id="student_middle_name" name="student_middle_name" value="{{ old('student_middle_name', $enrollment->student->middle_name) }}" class="block w-full rounded-lg border-border px-3 py-3">
                    </x-ui.field>
                    @if($enrollment->student_category === 'transferee')
                        <x-ui.field for="previous_school_name" label="Previous school">
                            <input id="previous_school_name" name="previous_school_name" required value="{{ old('previous_school_name', $enrollment->previous_school_name) }}" class="block w-full rounded-lg border-border px-3 py-3">
                        </x-ui.field>
                    @endif
                    <div class="sm:col-span-2"><x-ui.button type="submit" variant="secondary">Save application details</x-ui.button></div>
                </form>
                <form method="POST" action="{{ route('enrollment.public.submit', $enrollment) }}" class="mt-6 border-t border-border pt-5">
                    @csrf
                    <input type="hidden" name="resume_token" value="{{ $resumeToken }}">
                    <p class="mb-3 text-sm text-muted">Submission locks the public draft for Registrar review. Confirm that the information above is complete.</p>
                    <x-ui.button type="submit">Submit application</x-ui.button>
                </form>
            </section>
        @else
            <section class="jca-card p-6"><h2 class="text-xl font-black text-brand-navy-900">Application received</h2><p class="mt-2 text-sm text-muted">JCA will review identity, duplicates, and the applicable requirement checklist. An account invitation is issued only after staff verification.</p></section>
        @endif

        @if($enrollment->requirements->isNotEmpty())
            <section class="jca-card p-6" aria-labelledby="requirements-title">
                <h2 id="requirements-title" class="text-xl font-black text-brand-navy-900">Requirements</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach($enrollment->requirements as $requirement)
                        <li class="flex justify-between gap-4 rounded-lg bg-canvas p-3"><span>{{ $requirement->documentType->name }}</span><strong>{{ ucfirst(str_replace('_', ' ', $requirement->status)) }}</strong></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.public>
