<?php

namespace App\Modules\SectionAssignment\Http\Controllers;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentProgressController
{
    public function __invoke(Request $request): View
    {
        $student = $request->user()->studentProfile;
        abort_unless($student !== null, 403);

        return view('section-assignment.student-progress', [
            'enrollments' => Enrollment::query()->where('student_id', $student->id)->with([
                'academicYear', 'gradeLevel', 'currentSectionAssignment.section.sectionSubjects.subject',
                'currentSectionAssignment.section.teachingAssignments' => fn ($query) => $query->active()->where('assignment_type', 'adviser')->with('staffProfile.user'),
            ])->latest()->get(),
        ]);
    }
}
