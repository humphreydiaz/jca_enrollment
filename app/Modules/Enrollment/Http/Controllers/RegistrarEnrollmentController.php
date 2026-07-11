<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Enrollment\Actions\ConnectApplicantIdentityAction;
use App\Modules\Enrollment\Actions\ReviewEnrollmentDuplicateAction;
use App\Modules\Enrollment\Actions\StaffVerifyEnrollmentContactAction;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Http\Requests\ReviewEnrollmentDuplicateRequest;
use App\Modules\Enrollment\Http\Requests\TransitionEnrollmentRequest;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RegistrarEnrollmentController
{
    public function index(): View
    {
        Gate::authorize('viewAny', Enrollment::class);
        $query = Enrollment::query()->with(['student', 'guardian', 'academicYear', 'gradeLevel', 'requirements.documentType']);
        if (request('status')) {
            $query->where('status', request('status'));
        }
        if (request('search')) {
            $search = '%'.request('search').'%';
            $query->where(fn ($builder) => $builder->where('application_number', 'like', $search)->orWhereHas('student', fn ($student) => $student->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search)));
        }

        return view('enrollment.registrar.index', ['enrollments' => $query->with(['potentialDuplicateStudent', 'potentialDuplicateGuardian'])->latest()->paginate(20)->withQueryString()]);
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

    public function reviewDuplicate(Enrollment $enrollment, ReviewEnrollmentDuplicateRequest $request, ReviewEnrollmentDuplicateAction $review): RedirectResponse
    {
        $data = $request->validated();
        $student = isset($data['student_public_id']) ? StudentProfile::query()->where('public_id', $data['student_public_id'])->firstOrFail() : null;
        $guardian = isset($data['guardian_public_id']) ? GuardianProfile::query()->where('public_id', $data['guardian_public_id'])->firstOrFail() : null;
        $review->execute($enrollment, $data['duplicate_decision'], $request->user(), $data['reason'], $student, $guardian);

        return back()->with('status', 'Duplicate review recorded.');
    }

    public function connect(Enrollment $enrollment, Request $request, ConnectApplicantIdentityAction $connect): RedirectResponse
    {
        Gate::authorize('update', $enrollment);
        $invitation = $connect->execute($enrollment, $request->user());
        $response = back()->with('status', $invitation ? 'Guardian account connected. Deliver the one-time invitation through the verified channel.' : 'Existing guardian account connected.');

        return $invitation ? $response->with('invitation_url', route('identity.invitations.show', ['invitation' => $invitation->invitation->public_id, 'token' => $invitation->token])) : $response;
    }
}
