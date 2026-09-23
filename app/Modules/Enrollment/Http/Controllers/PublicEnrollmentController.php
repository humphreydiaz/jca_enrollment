<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\ResendEnrollmentContactVerificationAction;
use App\Modules\Enrollment\Actions\SaveEnrollmentDraftAction;
use App\Modules\Enrollment\Actions\StartEnrollmentAction;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Actions\VerifyEnrollmentContactAction;
use App\Modules\Enrollment\Http\Requests\StartEnrollmentRequest;
use App\Modules\Enrollment\Http\Requests\UpdateEnrollmentDraftRequest;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Support\LearnerProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicEnrollmentController
{
    public function create(Request $request): View|RedirectResponse
    {
        $activeApplication = $this->activeStudentApplication($request);
        if ($activeApplication !== null) {
            return redirect()
                ->route('enrollment.public.status', $activeApplication)
                ->with('status', 'You already have an active application for this school year.');
        }

        $studentSelfApplicant = $request->user()?->hasRole('student') ?? false;

        return view('enrollment.public.start', [
            'years' => AcademicYear::query()->whereIn('status', ['planned', 'open'])->orderByDesc('starts_on')->get(),
            'grades' => GradeLevel::query()
                ->where('is_active', true)
                ->availableForApplicant($studentSelfApplicant)
                ->orderBy('sequence')
                ->get(),
            'sexes' => LearnerProfileOptions::SEXES,
            'guardianRelationships' => LearnerProfileOptions::GUARDIAN_RELATIONSHIPS,
            'disabilityTypes' => LearnerProfileOptions::DISABILITY_TYPES,
        ]);
    }

    public function store(StartEnrollmentRequest $request, StartEnrollmentAction $start): RedirectResponse
    {
        $studentApplicant = $request->user()?->hasRole('student') ? $request->user() : null;
        $started = $start->execute($request->validated(), $studentApplicant);

        $redirect = redirect()
            ->route('enrollment.public.status', $this->statusRouteParameters($started->enrollment, $studentApplicant === null ? $started->resumeToken : ''))
            ->with('status', 'Draft application saved. Verify the contact to continue.');

        if (app()->environment(['local', 'testing'])) {
            $redirect->with('verification_code', $started->verificationCode);
        }

        return $redirect;
    }

    public function status(Enrollment $enrollment, Request $request): View
    {
        $token = $this->authorizeResume($enrollment, $request);

        return view('enrollment.public.status', [
            'enrollment' => $enrollment->load(['student', 'guardian', 'academicYear', 'gradeLevel', 'requirements.documentType', 'requirements.activeUpload', 'history']),
            'resumeToken' => $token,
            'guardianRelationships' => LearnerProfileOptions::GUARDIAN_RELATIONSHIPS,
        ]);
    }

    public function verify(Enrollment $enrollment, Request $request, VerifyEnrollmentContactAction $verify): RedirectResponse
    {
        $token = $this->authorizeResume($enrollment, $request);
        $data = $request->validate(['verification_code' => ['required', 'digits:6']]);
        $verify->execute($enrollment, $data['verification_code']);

        return redirect()->route('enrollment.public.status', $this->statusRouteParameters($enrollment, $token))->with('status', 'Contact verified. You can now complete and submit the draft.');
    }

    public function resendVerification(Enrollment $enrollment, Request $request, ResendEnrollmentContactVerificationAction $resend): RedirectResponse
    {
        $token = $this->authorizeResume($enrollment, $request);
        abort_if($enrollment->contact_verified_at !== null, 403);

        // Per-application cooldown so codes (and SMS credits) can't be spammed.
        if (! Cache::add('enroll-verify-resend:'.$enrollment->id, true, now()->addSeconds(60))) {
            throw ValidationException::withMessages([
                'verification_code' => 'A code was just sent. Please wait a minute before requesting another.',
            ]);
        }

        $code = $resend->execute($enrollment);

        $redirect = redirect()
            ->route('enrollment.public.status', $this->statusRouteParameters($enrollment, $token))
            ->with('status', 'A new verification code has been sent to your contact.');

        if (app()->environment(['local', 'testing'])) {
            $redirect->with('verification_code', $code);
        }

        return $redirect;
    }

    public function update(Enrollment $enrollment, UpdateEnrollmentDraftRequest $request, SaveEnrollmentDraftAction $save): RedirectResponse
    {
        $token = $this->authorizeResume($enrollment, $request);
        abort_unless($enrollment->contact_verified_at !== null, 403);
        $save->execute($enrollment, $request->validated());

        return redirect()->route('enrollment.public.status', $this->statusRouteParameters($enrollment, $token))->with('status', 'Draft details saved.');
    }

    public function submit(Enrollment $enrollment, Request $request, TransitionEnrollmentAction $transition): RedirectResponse
    {
        $token = $this->authorizeResume($enrollment, $request);
        abort_unless($enrollment->contact_verified_at !== null, 403);

        if (blank($enrollment->guardian_relationship) || ($enrollment->student_category === 'transferee' && (blank($enrollment->previous_school_name) || blank($enrollment->previous_school_address)))) {
            throw ValidationException::withMessages(['application' => 'Complete and save all required application details before submission.']);
        }

        $actor = $enrollment->student_applicant_user_id === $request->user()?->id ? $request->user() : null;
        $transition->execute($enrollment, 'submitted', $actor);

        return redirect()->route('enrollment.public.status', $this->statusRouteParameters($enrollment, $token))->with('status', 'Application submitted for Registrar review.');
    }

    private function authorizeResume(Enrollment $enrollment, Request $request): string
    {
        if ($enrollment->student_applicant_user_id !== null
            && $request->user()?->id === $enrollment->student_applicant_user_id
            && $request->user()->hasRole('student')) {
            return '';
        }

        $token = (string) ($request->input('resume_token') ?: $request->query('token'));
        abort_unless(strlen($token) === 64 && $enrollment->resume_token_hash !== null && hash_equals($enrollment->resume_token_hash, hash('sha256', $token)), 404);

        return $token;
    }

    /** @return array<string, Enrollment|string> */
    private function statusRouteParameters(Enrollment $enrollment, string $token): array
    {
        return array_filter([
            'enrollment' => $enrollment,
            'token' => $token,
        ], fn (Enrollment|string $value): bool => ! is_string($value) || $value !== '');
    }

    private function activeStudentApplication(Request $request): ?Enrollment
    {
        $user = $request->user();
        if ($user === null || ! $user->hasRole('student')) {
            return null;
        }

        return Enrollment::query()
            ->where('student_applicant_user_id', $user->id)
            ->whereNotIn('status', Enrollment::TERMINAL_STATUSES)
            ->latest()
            ->first();
    }
}
