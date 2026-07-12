<?php

namespace App\Modules\People\Http\Controllers;

use App\Models\User;
use App\Modules\People\Actions\CreateGuardianProfileAction;
use App\Modules\People\Actions\CreateStudentProfileAction;
use App\Modules\People\Actions\LinkGuardianStudentAction;
use App\Modules\People\Actions\VerifyGuardianStudentLinkAction;
use App\Modules\People\Http\Requests\CreateGuardianRequest;
use App\Modules\People\Http\Requests\CreateStudentRequest;
use App\Modules\People\Http\Requests\LinkGuardianStudentRequest;
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
            'students' => StudentProfile::query()->withCount('guardians')->orderBy('last_name')->get(),
            'links' => GuardianStudent::query()->with(['guardian', 'student'])->latest()->get(),
            'guardianUsers' => User::query()->whereHas('roles', fn ($query) => $query->where('code', 'guardian'))->orderBy('name')->get(),
            'studentUsers' => User::query()->whereHas('roles', fn ($query) => $query->where('code', 'student'))->orderBy('name')->get(),
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
