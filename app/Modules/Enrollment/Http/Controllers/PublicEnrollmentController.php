<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\StartEnrollmentAction;
use App\Modules\Enrollment\Http\Requests\StartEnrollmentRequest;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicEnrollmentController
{
    public function create(): View
    {
        return view('enrollment.public.start', ['years' => AcademicYear::query()->whereIn('status', ['planned', 'open'])->orderByDesc('starts_on')->get(), 'grades' => GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get()]);
    }

    public function store(StartEnrollmentRequest $request, StartEnrollmentAction $start): RedirectResponse
    {
        $record = $start->execute($request->validated());

        return redirect()->route('enrollment.public.status', $record)->with('status', 'Draft application saved. JCA must verify the contact before it can be submitted.');
    }

    public function status(Enrollment $enrollment): View
    {
        return view('enrollment.public.status', ['enrollment' => $enrollment]);
    }
}
