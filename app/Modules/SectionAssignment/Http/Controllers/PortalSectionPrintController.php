<?php

namespace App\Modules\SectionAssignment\Http\Controllers;

use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalSectionPrintController
{
    public function __invoke(string $enrollment, Request $request): View
    {
        $record = Enrollment::query()->where('public_id', $enrollment)->firstOrFail();
        abort_unless(Gate::forUser($request->user())->allows('view', $record), 404);
        // Current (not ended) adviser and subject teachers of the section; past school years keep showing who taught them.
        $record->load([
            'student', 'academicYear', 'gradeLevel',
            'currentSectionAssignment.section.sectionSubjects' => SectionSubject::scheduleWithTeachers(),
            'currentSectionAssignment.section.teachingAssignments' => fn ($query) => $query->where('is_active', true)->where('assignment_type', 'adviser')->with('staffProfile.user'),
        ]);

        return view('section-assignment.print', ['enrollment' => $record]);
    }
}
