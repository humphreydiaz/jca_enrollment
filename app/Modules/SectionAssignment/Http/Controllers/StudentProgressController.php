<?php

namespace App\Modules\SectionAssignment\Http\Controllers;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Support\StudentAccountIdentityMatcher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentProgressController
{
    public function __invoke(Request $request, StudentAccountIdentityMatcher $identityMatcher): View
    {
        $student = $request->user()->studentProfile;

        if ($student === null) {
            $studentApplications = Enrollment::query()
                ->where('student_applicant_user_id', $request->user()->id)
                ->with([
                    'academicYear',
                    'gradeLevel',
                    'requirements.documentType',
                    'requirements.activeUpload',
                    'history' => fn ($query) => $query->orderBy('changed_at'),
                ])
                ->latest()
                ->get();

            return view('section-assignment.student-progress', [
                'enrollments' => collect(),
                'connectionState' => 'unlinked',
                'studentApplications' => $studentApplications,
            ]);
        }

        if (! $identityMatcher->matches($student, $request->user())) {
            return view('section-assignment.student-progress', [
                'enrollments' => collect(),
                'connectionState' => 'identity_mismatch',
                'studentApplications' => collect(),
            ]);
        }

        return view('section-assignment.student-progress', [
            'connectionState' => 'connected',
            'studentApplications' => collect(),
            'enrollments' => Enrollment::query()->where('student_id', $student->id)->with([
                'academicYear', 'gradeLevel', 'requirements.documentType', 'requirements.activeUpload', 'currentSectionAssignment.section.sectionSubjects.subject',
                'currentSectionAssignment.section.teachingAssignments' => fn ($query) => $query->active()->where('assignment_type', 'adviser')->with('staffProfile.user'),
            ])->latest()->get(),
        ]);
    }
}
