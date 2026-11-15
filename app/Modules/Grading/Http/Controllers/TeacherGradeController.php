<?php

namespace App\Modules\Grading\Http\Controllers;

use App\Modules\Academics\Http\Requests\TeacherWorkspaceRequest;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Actions\RequestGradeCorrectionAction;
use App\Modules\Grading\Actions\SaveQuarterGradesAction;
use App\Modules\Grading\Actions\SubmitGradeAction;
use App\Modules\Grading\Http\Requests\GradeCorrectionRequest;
use App\Modules\Grading\Http\Requests\SaveQuarterGradesRequest;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Queries\TeacherGradebookQuery;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeacherGradeController
{
    public function index(TeacherWorkspaceRequest $request, TeacherGradebookQuery $gradebook): View
    {
        Gate::authorize('viewTeacherRoster', StudentSubjectGrade::class);
        Gate::authorize('viewAny', TeachingAssignment::class);

        return view('grading.teacher-index', $gradebook->forUser($request->user(), $request->validated('year')));
    }

    public function show(Request $request, TeachingAssignment $assignment, AuditRecorder $audit): View
    {
        Gate::authorize('viewTeacherRoster', StudentSubjectGrade::class);
        Gate::authorize('view', $assignment);
        $assignment->load(['section.academicYear', 'section.gradeLevel', 'sectionSubject.subject']);
        abort_unless($assignment->assignment_type === 'subject_teacher'
            && $assignment->sectionSubject !== null
            && $assignment->sectionSubject->section_id === $assignment->section_id
            && preg_match('/^G(?:[1-9]|10)$/', (string) $assignment->section->gradeLevel->code)
            && TeachingAssignment::query()->active()->forUser($request->user())->whereKey($assignment->id)->exists(), 403);

        $roster = SectionAssignment::query()->where('section_id', $assignment->section_id)
            ->where('status', 'confirmed')->whereNull('ended_at')
            ->whereHas('enrollment', fn ($query) => $query->where('status', 'enrolled'))
            ->with('enrollment.student')->get();
        $grades = StudentSubjectGrade::query()->whereIn('enrollment_id', $roster->pluck('enrollment_id'))
            ->where('subject_id', $assignment->sectionSubject->subject_id)
            ->with('corrections')
            ->get()->keyBy('enrollment_id');
        $audit->record($request->user(), 'grading.teacher.roster_viewed', $assignment);

        return view('grading.teacher-show', compact('assignment', 'roster', 'grades'));
    }

    public function save(SaveQuarterGradesRequest $request, TeachingAssignment $assignment, Enrollment $enrollment, SaveQuarterGradesAction $save): RedirectResponse
    {
        Gate::authorize('create', [StudentSubjectGrade::class, $assignment, $enrollment]);
        $save->execute($assignment, $enrollment, $request->user(), $request->validated());

        return back()->with('status', 'Quarter grades saved. The final grade is calculated when all four are present.');
    }

    public function submit(Request $request, TeachingAssignment $assignment, StudentSubjectGrade $grade, SubmitGradeAction $submit): RedirectResponse
    {
        Gate::authorize('update', [$grade, $assignment]);
        $submit->execute($grade, $assignment, $request->user());

        return back()->with('status', 'Complete grade submitted for Admin finalization.');
    }

    public function requestCorrection(GradeCorrectionRequest $request, TeachingAssignment $assignment, StudentSubjectGrade $grade, RequestGradeCorrectionAction $action): RedirectResponse
    {
        Gate::authorize('update', [$grade, $assignment]);
        $action->execute($grade, $assignment, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Correction request sent to the Admin.');
    }
}
