<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrarEnrollmentController
{
    public function index(): View
    {
        return view('enrollment.registrar.index', ['enrollments' => Enrollment::query()->with(['student', 'guardian', 'academicYear', 'gradeLevel', 'requirements.documentType'])->latest()->paginate(20)]);
    }

    public function verify(Enrollment $enrollment, Request $request): RedirectResponse
    {
        $enrollment->update(['contact_verified_at' => now()]);

        return back()->with('status', 'Contact marked verified after staff review.');
    }

    public function transition(Enrollment $enrollment, Request $request, TransitionEnrollmentAction $transition): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $transition->execute($enrollment, $data['status'], $request->user(), $data['reason'] ?? '');

        return back()->with('status', 'Enrollment status updated.');
    }
}
