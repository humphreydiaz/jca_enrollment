<?php

namespace App\Modules\People\Http\Controllers;

use App\Models\User;
use App\Modules\People\Actions\CreateGuardianProfileAction;
use App\Modules\People\Actions\CreateStudentProfileAction;
use App\Modules\People\Actions\DeleteUnusedStudentProfileAction;
use App\Modules\People\Actions\DisconnectStudentAccountAction;
use App\Modules\People\Actions\LinkGuardianStudentAction;
use App\Modules\People\Actions\LinkStudentAccountAction;
use App\Modules\People\Actions\VerifyGuardianStudentLinkAction;
use App\Modules\People\Http\Requests\CreateGuardianRequest;
use App\Modules\People\Http\Requests\CreateStudentRequest;
use App\Modules\People\Http\Requests\DeleteStudentProfileRequest;
use App\Modules\People\Http\Requests\DisconnectStudentAccountRequest;
use App\Modules\People\Http\Requests\LinkGuardianStudentRequest;
use App\Modules\People\Http\Requests\LinkStudentAccountRequest;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PeopleManagementController
{
    public function index(): View
    {
        return view('people.admin.index', [
            'guardians' => GuardianProfile::query()->withCount('students')->orderBy('last_name')->get(),
            'students' => StudentProfile::query()
                ->with(['user', 'enrollments.academicYear', 'enrollments.gradeLevel'])
                ->withCount(['guardians', 'guardianLinks', 'potentialDuplicateEnrollments'])
                ->orderBy('last_name')
                ->paginate(8, ['*'], 'students_page')
                ->withQueryString(),
            // Full, lightweight list for the "Link guardian and student" dropdown,
            // which must offer every student regardless of the paginated view above.
            'studentOptions' => StudentProfile::query()->orderBy('last_name')->get(),
            'links' => GuardianStudent::query()->with(['guardian', 'student'])->latest()->paginate(10, ['*'], 'links_page')->withQueryString(),
            'guardianUsers' => User::query()->whereHas('roles', fn ($query) => $query->where('code', 'guardian'))->orderBy('name')->get(),
            'studentUsers' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('code', 'student'))
                ->whereDoesntHave('studentProfile')
                ->whereIn('status', ['invited', 'active'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeGuardian(CreateGuardianRequest $request, CreateGuardianProfileAction $createGuardian): RedirectResponse
    {
        $createGuardian->execute($request->validated(), $request->user());

        return back()->with('status', 'Guardian profile created.');
    }

    public function storeStudent(CreateStudentRequest $request, CreateStudentProfileAction $createStudent): RedirectResponse
    {
        $createStudent->execute($request->validated(), $request->user());

        return back()->with('status', 'Student profile created.');
    }

    public function destroyStudent(
        StudentProfile $student,
        DeleteStudentProfileRequest $request,
        DeleteUnusedStudentProfileAction $deleteStudent,
    ): RedirectResponse {
        $deleteStudent->execute($student, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Unused mistaken student profile removed. Enrollment and duplicate-review history were preserved.');
    }

    public function linkStudentAccount(
        StudentProfile $student,
        LinkStudentAccountRequest $request,
        LinkStudentAccountAction $linkStudentAccount,
    ): RedirectResponse {
        $account = User::query()->where('public_id', $request->validated('user_public_id'))->firstOrFail();
        $linkStudentAccount->execute(
            $student,
            $account,
            $request->user(),
            $request->validated('exception_reason'),
        );

        return back()->with('status', 'Student account connected. The student portal is now available for this profile.');
    }

    public function disconnectStudentAccount(
        StudentProfile $student,
        DisconnectStudentAccountRequest $request,
        DisconnectStudentAccountAction $disconnectStudentAccount,
    ): RedirectResponse {
        $disconnectStudentAccount->execute($student, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Student account disconnected and its active sessions revoked. The student profile and enrollment history were preserved.');
    }

    public function link(LinkGuardianStudentRequest $request, LinkGuardianStudentAction $linkGuardianStudent): RedirectResponse
    {
        $validated = $request->validated();
        $guardian = GuardianProfile::query()->where('public_id', $validated['guardian_public_id'])->firstOrFail();
        $student = StudentProfile::query()->where('public_id', $validated['student_public_id'])->firstOrFail();

        $linkGuardianStudent->execute($guardian, $student, $validated, $request->user());

        return back()->with('status', 'Guardian and student linked.');
    }

    public function verify(GuardianProfile $guardian, StudentProfile $student, VerifyGuardianStudentLinkAction $verifyLink): RedirectResponse
    {
        $link = GuardianStudent::query()
            ->where('guardian_id', $guardian->id)
            ->where('student_id', $student->id)
            ->firstOrFail();
        $verifyLink->execute($link, request()->user());

        return back()->with('status', 'Guardian relationship verified.');
    }
}
