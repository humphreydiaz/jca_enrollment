<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\SaveEnrollmentDraftAction;
use App\Modules\Enrollment\Actions\StartEnrollmentAction;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Actions\VerifyEnrollmentContactAction;
use App\Modules\Enrollment\Http\Requests\StartEnrollmentRequest;
use App\Modules\Enrollment\Http\Requests\UpdateEnrollmentDraftRequest;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicEnrollmentController
{
    public function create(): View
    {
        return view('enrollment.public.start', ['years' => AcademicYear::query()->whereIn('status', ['planned', 'open'])->orderByDesc('starts_on')->get(), 'grades' => GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get()]);
    }

    public function store(StartEnrollmentRequest $request, StartEnrollmentAction $start): RedirectResponse
    {
        $started = $start->execute($request->validated());

        $redirect = redirect()->route('enrollment.public.status', [
            'enrollment' => $started->enrollment,
            'token' => $started->resumeToken,
        ])->with('status', 'Draft application saved. Verify the contact to continue.');

        if (app()->environment(['local', 'testing'])) {
            $redirect->with('verification_code', $started->verificationCode);
        }

        return $redirect;
    }

    public function status(Enrollment $enrollment, Request $request): View
    {
        $token = $this->authorizeResume($enrollment, $request);

        return view('enrollment.public.status', ['enrollment' => $enrollment->load(['student', 'guardian', 'academicYear', 'gradeLevel', 'requirements.documentType']), 'resumeToken' => $token]);
    }

    public function verify(Enrollment $enrollment, Request $request, VerifyEnrollmentContactAction $verify): RedirectResponse
    {
        $token = $this->authorizeResume($enrollment, $request);
        $data = $request->validate(['verification_code' => ['required', 'digits:6']]);
        $verify->execute($enrollment, $data['verification_code']);

        return redirect()->route('enrollment.public.status', ['enrollment' => $enrollment, 'token' => $token])->with('status', 'Contact verified. You can now complete and submit the draft.');
    }

    public function update(Enrollment $enrollment, UpdateEnrollmentDraftRequest $request, SaveEnrollmentDraftAction $save): RedirectResponse
    {
        $token = $this->authorizeResume($enrollment, $request);
        abort_unless($enrollment->contact_verified_at !== null, 403);
        $save->execute($enrollment, $request->validated());

        return redirect()->route('enrollment.public.status', ['enrollment' => $enrollment, 'token' => $token])->with('status', 'Draft details saved.');
    }

    public function submit(Enrollment $enrollment, Request $request, TransitionEnrollmentAction $transition): RedirectResponse
    {
        $token = $this->authorizeResume($enrollment, $request);
        abort_unless($enrollment->contact_verified_at !== null, 403);

        if (blank($enrollment->guardian_relationship) || ($enrollment->student_category === 'transferee' && blank($enrollment->previous_school_name))) {
            throw ValidationException::withMessages(['application' => 'Complete and save all required application details before submission.']);
        }

        $transition->execute($enrollment, 'submitted', null);

        return redirect()->route('enrollment.public.status', ['enrollment' => $enrollment, 'token' => $token])->with('status', 'Application submitted for Registrar review.');
    }

    private function authorizeResume(Enrollment $enrollment, Request $request): string
    {
        $token = (string) ($request->input('resume_token') ?: $request->query('token'));
        abort_unless(strlen($token) === 64 && $enrollment->resume_token_hash !== null && hash_equals($enrollment->resume_token_hash, hash('sha256', $token)), 404);

        return $token;
    }
}
