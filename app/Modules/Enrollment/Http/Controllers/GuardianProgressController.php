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

        return view('enrollment.guardian.progress', ['enrollments' => Enrollment::query()->where(fn ($query) => $query->where('applicant_guardian_id', $guardian->id)->orWhereHas('student.guardians', fn ($guardians) => $guardians->whereKey($guardian->id)->whereNotNull('guardian_student.verified_at')->where('guardian_student.may_manage_enrollment', true)))->with([
            'student', 'academicYear', 'gradeLevel', 'requirements.documentType', 'requirements.activeUpload',
            'currentSectionAssignment.section.sectionSubjects.subject',
            'currentSectionAssignment.section.teachingAssignments' => fn ($query) => $query->active()->where('assignment_type', 'adviser')->with('staffProfile.user'),
        ])->latest()->get()]);
    }
}
