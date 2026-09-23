<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\ConnectApplicantIdentityAction;
use App\Modules\Enrollment\Actions\ReviewEnrollmentDuplicateAction;
use App\Modules\Enrollment\Actions\StaffVerifyEnrollmentContactAction;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Http\Requests\BulkTransitionEnrollmentRequest;
use App\Modules\Enrollment\Http\Requests\ReviewEnrollmentDuplicateRequest;
use App\Modules\Enrollment\Http\Requests\TransitionEnrollmentRequest;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\Identity\Actions\SendInvitationSmsAction;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegistrarEnrollmentController
{
    public function index(): View
    {
        Gate::authorize('viewAny', Enrollment::class);
        $gradeId = request('grade') ? GradeLevel::query()->where('public_id', request('grade'))->value('id') : null;

        $query = Enrollment::query()->with(['student', 'guardian', 'academicYear', 'gradeLevel', 'requirements.documentType', 'requirements.receivedBy', 'requirements.softCopyReviewedBy', 'requirements.activeUpload', 'requirements.carriedFromRequirement.enrollment.academicYear', 'requirements.carriedFromRequirement.enrollment.gradeLevel']);
        if (request('status')) {
            $query->where('status', request('status'));
        }
        if ($gradeId !== null) {
            $query->where('grade_level_id', $gradeId);
        }
        if (request('search')) {
            $search = '%'.request('search').'%';
            $query->where(fn ($builder) => $builder->where('application_number', 'like', $search)->orWhereHas('student', fn ($student) => $student->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search)));
        }

        $enrollments = $query->with(['potentialDuplicateStudent', 'potentialDuplicateGuardian'])->latest()->paginate(20)->withQueryString();
        $allowedTransitions = $enrollments->getCollection()->mapWithKeys(
            fn (Enrollment $enrollment): array => [$enrollment->id => TransitionEnrollmentAction::allowedTransitionsFrom($enrollment->status)],
        );

        $statusCounts = Enrollment::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $summary = [
            'total' => (int) $statusCounts->sum(),
            'needs_review' => (int) $statusCounts->only(['submitted', 'under_review', 'incomplete', 'assessment_required', 'assessment_pending'])->sum(),
            'waitlisted' => (int) ($statusCounts['waitlisted'] ?? 0),
            'approved' => (int) ($statusCounts['approved'] ?? 0),
        ];

        $filterGrades = GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get();

        return view('enrollment.registrar.index', compact('enrollments', 'allowedTransitions', 'summary', 'filterGrades'));
    }

    public function verify(Enrollment $enrollment, Request $request, StaffVerifyEnrollmentContactAction $verify): RedirectResponse
    {
        Gate::authorize('update', $enrollment);
        $verify->execute($enrollment, $request->user());

        return back()->with('status', 'Contact marked verified after staff review.');
    }

    public function transition(Enrollment $enrollment, TransitionEnrollmentRequest $request, TransitionEnrollmentAction $transition): RedirectResponse
    {
        $data = $request->validated();
        $transition->execute($enrollment, $data['status'], $request->user(), $data['reason'] ?? '');

        return back()->with('status', 'Enrollment status updated.');
    }

    public function bulkTransition(BulkTransitionEnrollmentRequest $request, TransitionEnrollmentAction $transition): RedirectResponse
    {
        Gate::authorize('viewAny', Enrollment::class);
        $data = $request->validated();
        $status = $data['status'];
        $reason = $data['reason'] ?? '';

        $applied = 0;
        $skipped = 0;

        foreach ($data['enrollment_public_ids'] as $publicId) {
            $enrollment = Enrollment::query()->where('public_id', $publicId)->first();
            if (! $enrollment || $request->user()->cannot('update', $enrollment)) {
                $skipped++;

                continue;
            }

            try {
                $transition->execute($enrollment, $status, $request->user(), $reason);
                $applied++;
            } catch (ValidationException|\Throwable) {
                $skipped++;
            }
        }

        $label = str($status)->replace('_', ' ')->headline();

        return back()->with('status', "Applied \"{$label}\" to {$applied} application(s). {$skipped} skipped (not eligible for this transition).");
    }

    public function reviewDuplicate(Enrollment $enrollment, ReviewEnrollmentDuplicateRequest $request, ReviewEnrollmentDuplicateAction $review): RedirectResponse
    {
        $data = $request->validated();
        $student = isset($data['student_public_id']) ? StudentProfile::query()->where('public_id', $data['student_public_id'])->firstOrFail() : null;
        $guardian = isset($data['guardian_public_id']) ? GuardianProfile::query()->where('public_id', $data['guardian_public_id'])->firstOrFail() : null;
        $review->execute($enrollment, $data['duplicate_decision'], $request->user(), $data['reason'], $student, $guardian);

        return back()->with('status', 'Duplicate review recorded.');
    }

    public function connect(Enrollment $enrollment, Request $request, ConnectApplicantIdentityAction $connect, SendInvitationSmsAction $sendInvitationSms): RedirectResponse
    {
        Gate::authorize('update', $enrollment);
        $invitation = $connect->execute($enrollment, $request->user());

        if ($invitation === null) {
            return back()->with('status', 'Existing guardian account connected.');
        }

        $setupUrl = route('identity.invitations.short', ['token' => $invitation->token]);

        // Auto-deliver the one-time setup link by SMS whenever the guardian has a mobile number.
        $mobile = ContactNormalizer::mobile($enrollment->fresh('guardian')?->guardian?->mobile);
        $smsSent = $mobile !== null && $sendInvitationSms->execute($invitation->invitation, $mobile, $invitation->token);

        $status = $smsSent
            ? 'Guardian account connected. The SMS service accepted the one-time code for delivery; this does not confirm receipt by the phone. A fallback code and link are shown below.'
            : 'Guardian account connected, but the SMS service did not accept the one-time code. Use the fallback code or link shown below.';

        return back()
            ->with('status', $status)
            ->with('invitation_code', $invitation->token)
            ->with('invitation_url', $setupUrl);
    }
}
