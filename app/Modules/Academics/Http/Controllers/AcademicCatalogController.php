<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Modules\Academics\Actions\CreateTeachingAssignmentAction;
use App\Modules\Academics\Actions\SaveAcademicCatalogAction;
use App\Modules\Academics\Http\Requests\AcademicYearRequest;
use App\Modules\Academics\Http\Requests\GradeLevelRequest;
use App\Modules\Academics\Http\Requests\SectionSubjectRequest;
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
use App\Modules\People\Models\StaffProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AcademicCatalogController
{
    public function index(): View
    {
        Gate::authorize('viewAny', AcademicYear::class);

        return view('academics.admin.index', [
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'gradeLevels' => GradeLevel::query()->with('nextGradeLevel')->orderBy('sequence')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'sections' => Section::query()->with(['academicYear', 'gradeLevel', 'sectionSubjects.subject', 'teachingAssignments.staffProfile.user'])
                ->orderByDesc('academic_year_id')->orderBy('grade_level_id')->orderBy('sort_order')->get(),
            'staffProfiles' => StaffProfile::query()->with('user')
                ->whereHas('user.roles', fn ($query) => $query->where('code', 'teacher'))
                ->where(fn ($query) => $query->whereNull('active_until')->orWhereDate('active_until', '>=', today()))
                ->orderBy('employee_number')->get(),
        ]);
    }

    public function storeAcademicYear(AcademicYearRequest $request, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('create', AcademicYear::class);
        $save->execute(new AcademicYear, $request->validated(), $request->user(), 'academics.academic_year.created');

        return back()->with('status', 'Academic year saved.');
    }

    public function updateAcademicYear(AcademicYearRequest $request, AcademicYear $academicYear, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('update', $academicYear);
        $save->execute($academicYear, $request->validated(), $request->user(), 'academics.academic_year.updated');

        return back()->with('status', 'Academic year updated. Historical records remain attached to this year.');
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
        $save->execute($gradeLevel, $request->validated(), $request->user(), 'academics.grade_level.updated');

        return back()->with('status', 'Grade level updated.');
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
        $save->execute($subject, $request->validated(), $request->user(), 'academics.subject.updated');

        return back()->with('status', 'Subject updated.');
    }

    public function storeSection(StoreSectionRequest $request, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('create', Section::class);
        $save->execute(new Section, $request->validated(), $request->user(), 'academics.section.created');

        return back()->with('status', 'Section saved with its configured capacity.');
    }

    public function updateSection(UpdateSectionRequest $request, Section $section, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('update', $section);
        $save->execute($section, $request->validated(), $request->user(), 'academics.section.updated');

        return back()->with('status', 'Section updated. No historical section record was deleted.');
    }

    public function storeSectionSubject(SectionSubjectRequest $request, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('create', Section::class);
        $save->execute(new SectionSubject, $request->safe()->only(['section_id', 'subject_id', 'schedule_text']), $request->user(), 'academics.section_subject.created');

        return back()->with('status', 'Section subject and schedule saved.');
    }

    public function updateSectionSubject(UpdateSectionSubjectRequest $request, SectionSubject $sectionSubject, SaveAcademicCatalogAction $save): RedirectResponse
    {
        Gate::authorize('update', $sectionSubject->section);
        $save->execute($sectionSubject, $request->validated(), $request->user(), 'academics.section_subject.updated');

        return back()->with('status', 'Section schedule updated.');
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
        $save->execute($teachingAssignment, $request->validated(), $request->user(), 'academics.teaching_assignment.updated');

        return back()->with('status', 'Teaching assignment updated.');
    }
}
