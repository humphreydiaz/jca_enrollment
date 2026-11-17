<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Enrollment\Actions\CorrectPreviousSchoolAction;
use App\Modules\Enrollment\Http\Requests\CorrectPreviousSchoolRequest;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\RedirectResponse;

class EnrollmentCorrectionController
{
    public function updatePreviousSchool(CorrectPreviousSchoolRequest $request, Enrollment $enrollment, CorrectPreviousSchoolAction $correct): RedirectResponse
    {
        $validated = $request->validated();
        $reason = $validated['reason'];
        unset($validated['reason']);
        $correct->execute($enrollment, $validated, $reason, $request->user());

        return back()->with('status', 'Previous-school information corrected and added to the audit history.');
    }
}
