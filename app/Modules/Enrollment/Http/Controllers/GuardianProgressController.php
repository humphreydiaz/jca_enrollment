<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardianProgressController
{
    public function __invoke(Request $request): View
    {
        $guardian = $request->user()->guardianProfile;
        abort_unless($guardian !== null, 403);

        return view('enrollment.guardian.progress', ['enrollments' => Enrollment::query()->where('applicant_guardian_id', $guardian->id)->with(['student', 'academicYear', 'gradeLevel', 'requirements.documentType'])->latest()->get()]);
    }
}
