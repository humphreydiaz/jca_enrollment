<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Communications\Actions\UpdateSmsConsentAction;
use App\Modules\Enrollment\Actions\SaveEnrollmentDraftAction;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Http\Requests\CompleteEnrollmentApplicationRequest;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Support\LearnerProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PublicEnrollmentController
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        $enrollment = Enrollment::query()
            ->where('status', 'draft')
            ->where(function ($query) use ($user) {
                $query->where('access_issued_to_user_id', $user->id)
                    ->orWhere('student_applicant_user_id', $user->id);

                if ($user->studentProfile) {
                    $query->orWhere(
                        'student_id',
                        $user->studentProfile->id
                    );
                }

                if ($user->guardianProfile) {
                    $guardian = $user->guardianProfile;

                    $query->orWhereHas('student.guardians', function ($q) use ($guardian) {
                        $q->whereKey($guardian->id)
                            ->whereNotNull('guardian_student.verified_at')
                            ->where('guardian_student.may_manage_enrollment', true);
                    });
                }
            })
            ->with([
                'student',
                'guardian',
                'academicYear',
                'gradeLevel',
            ])
            ->when($request->filled('enrollment'), function ($query) use ($request) {
                $query->where('public_id', $request->query('enrollment'));
            })
            ->latest('id')
            ->get()
            ->first(fn (Enrollment $record) => $user->can('update', $record));

        if (! $enrollment) {
            return redirect()
                ->route('identity.dashboard')
                ->withErrors([
                    'application' => 'No editable draft application is available for this account.',
                ]);
        }

        if ($this->isEnrollmentClosed($enrollment)) {
            return redirect()
                ->route('enrollment.public.status', $enrollment)
                ->withErrors([
                    'application' => 'Enrollment is currently closed. Editing and submission are disabled.',
                ]);
        }

        return view('enrollment.public.start', [
            'enrollment' => $enrollment,
            'years' => collect([$enrollment->academicYear]),
            'grades' => collect([$enrollment->gradeLevel]),
            'sexes' => LearnerProfileOptions::SEXES,
            'guardianRelationships' => LearnerProfileOptions::GUARDIAN_RELATIONSHIPS,
            'disabilityTypes' => LearnerProfileOptions::DISABILITY_TYPES,
            'smsOptIn' => [
                'guardian' => UpdateSmsConsentAction::granted($enrollment->guardian),
                'student' => UpdateSmsConsentAction::granted($enrollment->student),
            ],
            'enrollmentClosed' => false,
        ]);
    }

    public function status(
        Enrollment $enrollment,
        Request $request
    ): View {
        Gate::authorize('view', $enrollment);

        $enrollment->loadMissing('academicYear');

        return view('enrollment.application.status', [
            'enrollment' => $enrollment->load([
                'student',
                'guardian',
                'academicYear',
                'gradeLevel',
                'requirements.documentType',
                'requirements.activeUpload',
                'history',
            ]),
            'guardianRelationships' => LearnerProfileOptions::GUARDIAN_RELATIONSHIPS,
            'enrollmentClosed' => $this->isEnrollmentClosed($enrollment),
        ]);
    }

    public function update(
        Enrollment $enrollment,
        CompleteEnrollmentApplicationRequest $request,
        SaveEnrollmentDraftAction $save
    ): RedirectResponse {
        Gate::authorize('update', $enrollment);

        $enrollment->loadMissing('academicYear');

        if ($this->isEnrollmentClosed($enrollment)) {
            return redirect()
                ->route('enrollment.public.status', $enrollment)
                ->withErrors([
                    'application' => 'Enrollment is closed. Changes to your application are no longer allowed.',
                ]);
        }

        $save->execute(
            $enrollment,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('enrollment.public.status', $enrollment)
            ->with(
                'status',
                'Application details saved. Review them, then submit the application.'
            );
    }

    public function submit(
        Enrollment $enrollment,
        Request $request,
        TransitionEnrollmentAction $transition
    ): RedirectResponse {
        Gate::authorize('update', $enrollment);

        $enrollment->loadMissing('academicYear');

        if ($this->isEnrollmentClosed($enrollment)) {
            return redirect()
                ->route('enrollment.public.status', $enrollment)
                ->withErrors([
                    'application' => 'Enrollment is closed. Application submission is disabled.',
                ]);
        }

        $transition->execute(
            $enrollment,
            'submitted',
            $request->user()
        );

        return redirect()
            ->route('enrollment.public.status', $enrollment)
            ->with(
                'status',
                'Application submitted for Registrar review.'
            );
    }

    private function isEnrollmentClosed(Enrollment $enrollment): bool
    {
        $enrollment->loadMissing('academicYear');

        $year = $enrollment->academicYear;

        if (
            ! $year ||
            $year->status !== 'open' ||
            ! $year->enrollment_is_open
        ) {
            return true;
        }

        $today = now('Asia/Manila')->toDateString();

        if (
            $year->enrollment_opens_on &&
            $today < $year->enrollment_opens_on->toDateString()
        ) {
            return true;
        }

        if (
            $year->enrollment_closes_on &&
            $today > $year->enrollment_closes_on->toDateString()
        ) {
            return true;
        }

        return false;
    }
}