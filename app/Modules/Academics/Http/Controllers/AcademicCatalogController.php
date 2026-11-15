<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Modules\Academics\Actions\AddMissingCurriculumSubjectsAction;
use App\Modules\Academics\Actions\CreateSectionAction;
use App\Modules\Academics\Actions\CreateTeachingAssignmentAction;
use App\Modules\Academics\Actions\DeleteUnusedAcademicRecordAction;
use App\Modules\Academics\Actions\SaveAcademicCatalogAction;
use App\Modules\Academics\Actions\SaveSectionSubjectAction;
use App\Modules\Academics\Actions\SetSectionSubjectStatusAction;
use App\Modules\Academics\Actions\UpdateSectionScheduleAction;
use App\Modules\Academics\Http\Requests\AcademicWorkspaceRequest;
use App\Modules\Academics\Http\Requests\AcademicYearRequest;
use App\Modules\Academics\Http\Requests\DeleteAcademicRecordRequest;
use App\Modules\Academics\Http\Requests\GradeLevelRequest;
use App\Modules\Academics\Http\Requests\SectionSubjectRequest;
use App\Modules\Academics\Http\Requests\SectionSubjectStatusRequest;
use App\Modules\Academics\Http\Requests\StoreSectionRequest;
use App\Modules\Academics\Http\Requests\StoreTeachingAssignmentRequest;
use App\Modules\Academics\Http\Requests\SubjectRequest;
use App\Modules\Academics\Http\Requests\UpdateSectionRequest;
use App\Modules\Academics\Http\Requests\UpdateSectionSubjectRequest;
use App\Modules\Academics\Http\Requests\UpdateTeachingAssignmentRequest;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Queries\AcademicWorkspaceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AcademicCatalogController
{
    /** Class schedules: one school year, filtered by grade and section. */
    public function index(AcademicWorkspaceRequest $request, AcademicWorkspaceQuery $query): View
    {
        Gate::authorize('viewAny', AcademicYear::class);

        return view('academics.admin.index', $query->classSchedules($request->validated()));
    }

    /** Setup: school years, grade levels, the subject catalog, and the selected year's sections. */
    public function setup(AcademicWorkspaceRequest $request, AcademicWorkspaceQuery $query): View
    {
        Gate::authorize('viewAny', AcademicYear::class);

        return view('academics.admin.setup', $query->setup($request->validated()));
    }

    public function storeAcademicYear(AcademicYearRequest $request, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('create', AcademicYear::class);
        $this->authorizeArchiveChange($request->validated('status') === 'archived', AcademicYear::class);
        $year = $save->execute(new AcademicYear, $request->validated(), $request->user(), 'academics.academic_year.created');

        return redirect()->route('academics.admin.index', ['year' => $year->public_id])
            ->with('status', 'School year saved. Next, load its class program from the curriculum.');
    }

    public function updateAcademicYear(AcademicYearRequest $request, AcademicYear $academicYear, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('update', $academicYear);
        $this->authorizeArchiveChange(($request->validated('status') === 'archived') !== ($academicYear->status === 'archived'), $academicYear);
        // Preserve manual enrollment control.
        // If the previous enrollment deadline has passed,
        // changing dates must not automatically reopen enrollment.
        $previousDeadlineExpired = $academicYear->enrollment_closes_on
            && now('Asia/Manila')->toDateString()
                > $academicYear->enrollment_closes_on->toDateString();

        $save->execute($academicYear, $request->validated(), $request->user(), 'academics.academic_year.updated');

        if ($previousDeadlineExpired || $academicYear->fresh()->status !== 'open') {
            $academicYear->update([
                'enrollment_is_open' => false,
            ]);
        }

        return back()->with('status', 'Academic year updated. Historical records remain attached to this year.');
    }

    public function setEnrollmentStatus(
        \Illuminate\Http\Request $request,
        AcademicYear $academicYear
    ): RedirectResponse {
        Gate::authorize('update', $academicYear);

        $data = $request->validate([
            'enrollment_is_open' => ['required', 'boolean'],
        ]);

        $open = (bool) $data['enrollment_is_open'];

        if ($open) {
            if ($academicYear->status !== 'open') {
                return back()->withErrors([
                    'enrollment' => 'The school year must be active before enrollment can be opened.',
                ]);
            }

            $today = now('Asia/Manila')->startOfDay();

            if (
                ($academicYear->enrollment_opens_on &&
                    $today->lt($academicYear->enrollment_opens_on)) ||
                ($academicYear->enrollment_closes_on &&
                    $today->gt($academicYear->enrollment_closes_on))
            ) {
                return back()->withErrors([
                    'enrollment' => 'Update the enrollment dates first, then open enrollment.',
                ]);
            }
        }

        $academicYear->update([
            'enrollment_is_open' => $open,
        ]);

        return back()->with(
            'status',
            $open ? 'Enrollment opened successfully.' : 'Enrollment closed successfully.'
        );
    }
    public function destroyAcademicYear(DeleteAcademicRecordRequest $request, AcademicYear $academicYear, DeleteUnusedAcademicRecordAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $academicYear);
        $delete->execute($academicYear, $request->user());

        return redirect()->route('academics.admin.setup')->with('status', 'School year deleted.');
    }

    public function storeGradeLevel(GradeLevelRequest $request, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('create', GradeLevel::class);
        $save->execute(new GradeLevel, $request->validated(), $request->user(), 'academics.grade_level.created');

        return back()->with('status', 'Grade level saved.');
    }

    public function updateGradeLevel(GradeLevelRequest $request, GradeLevel $gradeLevel, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('update', $gradeLevel);
        $this->authorizeArchiveChange($request->boolean('is_active') !== $gradeLevel->is_active, $gradeLevel);
        $save->execute($gradeLevel, $request->validated(), $request->user(), 'academics.grade_level.updated');

        return back()->with('status', 'Grade level updated.');
    }

    public function destroyGradeLevel(DeleteAcademicRecordRequest $request, GradeLevel $gradeLevel, DeleteUnusedAcademicRecordAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $gradeLevel);
        $delete->execute($gradeLevel, $request->user());

        return back()->with('status', 'Grade level deleted.');
    }

    public function storeSubject(SubjectRequest $request, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('create', Subject::class);
        $save->execute(new Subject, $request->validated(), $request->user(), 'academics.subject.created');

        return back()->with('status', 'Subject saved.');
    }

    public function updateSubject(SubjectRequest $request, Subject $subject, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('update', $subject);
        $this->authorizeArchiveChange($request->boolean('is_active') !== $subject->is_active, $subject);
        $save->execute($subject, $request->validated(), $request->user(), 'academics.subject.updated');

        return back()->with('status', 'Subject updated.');
    }

    public function destroySubject(DeleteAcademicRecordRequest $request, Subject $subject, DeleteUnusedAcademicRecordAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $subject);
        $delete->execute($subject, $request->user());

        return back()->with('status', 'Subject deleted.');
    }

    public function storeSection(StoreSectionRequest $request, CreateSectionAction $create): RedirectResponse
    {
        Gate::authorize('create', Section::class);
        $this->authorizeArchiveChange($request->validated('status') === 'inactive', Section::class);
        $section = $create->execute($request->validated(), $request->user());

        return redirect()->route('academics.admin.index', ['year' => $section->academicYear->public_id, 'section' => $section->public_id])
            ->with('status', $section->allSectionSubjects()->exists()
                ? 'Section saved with its grade subjects from the published curriculum.'
                : 'Section saved with its configured capacity.');
    }

    public function updateSection(UpdateSectionRequest $request, Section $section, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('update', $section);
        $this->authorizeArchiveChange($request->validated('status') !== $section->status, $section);
        $save->execute($section, $request->validated(), $request->user(), 'academics.section.updated');

        return back()->with('status', 'Section updated. No historical section record was deleted.');
    }

    public function destroySection(DeleteAcademicRecordRequest $request, Section $section, DeleteUnusedAcademicRecordAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $section);
        $year = $section->academicYear;
        $grade = $section->gradeLevel;
        $delete->execute($section, $request->user());

        return redirect()->route('academics.admin.index', ['year' => $year->public_id, 'grade' => $grade->public_id])
            ->with('status', 'Section deleted.');
    }

    public function addCurriculumSubjects(Section $section, AddMissingCurriculumSubjectsAction $add): RedirectResponse
    {
        Gate::authorize('update', $section);
        $added = $add->execute($section, request()->user());

        return back()->with('status', $added === 0
            ? 'This section already offers every subject in the published curriculum.'
            : $added.' missing curriculum '.str('subject')->plural($added).' added.');
    }

    public function storeSectionSubject(SectionSubjectRequest $request, SaveSectionSubjectAction $save): RedirectResponse
    {
        Gate::authorize('create', Section::class);
        $save->execute($request->validated(), $request->user());

        return back()->with('status', 'Subject added to the section.');
    }

    public function updateSectionSubject(UpdateSectionSubjectRequest $request, SectionSubject $sectionSubject, UpdateSectionScheduleAction $update): RedirectResponse
    {
        Gate::authorize('update', $sectionSubject->section);
        $update->execute($sectionSubject, $request->validated(), $request->user());

        return back()->with('status', 'Class time updated.');
    }

    public function setSectionSubjectStatus(SectionSubjectStatusRequest $request, SectionSubject $sectionSubject, SetSectionSubjectStatusAction $status): RedirectResponse
    {
        $status->execute($sectionSubject, $request->validated('status'), $request->user());

        return back()->with('status', $request->validated('status') === 'archived'
            ? 'Subject archived for this section. Its history and grades were kept.'
            : 'Subject restored for this section.');
    }

    public function destroySectionSubject(DeleteAcademicRecordRequest $request, SectionSubject $sectionSubject, DeleteUnusedAcademicRecordAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $sectionSubject->section);
        $delete->execute($sectionSubject, $request->user());

        return back()->with('status', 'Subject removed from the section.');
    }

    public function storeTeachingAssignment(StoreTeachingAssignmentRequest $request, CreateTeachingAssignmentAction $create): RedirectResponse
    {
        Gate::authorize('create', TeachingAssignment::class);
        $create->execute($request->validated(), $request->user());

        return back()->with('status', 'Teaching assignment saved.');
    }

    public function updateTeachingAssignment(UpdateTeachingAssignmentRequest $request, TeachingAssignment $teachingAssignment, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('update', $teachingAssignment);
        $this->authorizeArchiveChange($request->boolean('is_active') !== $teachingAssignment->is_active, $teachingAssignment);
        $save->execute($teachingAssignment, $request->validated(), $request->user(), 'academics.teaching_assignment.updated');

        return back()->with('status', 'Teaching assignment updated.');
    }

    public function destroyTeachingAssignment(DeleteAcademicRecordRequest $request, TeachingAssignment $teachingAssignment, DeleteUnusedAcademicRecordAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $teachingAssignment);
        $delete->execute($teachingAssignment, $request->user());

        return back()->with('status', 'Teaching assignment deleted.');
    }

    /** Archiving, deactivating, or restoring a record is reserved for the Admin. */
    private function authorizeArchiveChange(bool $changesArchiveState, mixed $subject): void
    {
        if ($changesArchiveState) {
            Gate::authorize('archive', $subject);
        }
    }
}
