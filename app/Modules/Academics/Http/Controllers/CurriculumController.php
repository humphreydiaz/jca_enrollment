<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Modules\Academics\Actions\CreateCurriculumDraftAction;
use App\Modules\Academics\Actions\DeleteCurriculumDraftAction;
use App\Modules\Academics\Actions\PublishCurriculumAction;
use App\Modules\Academics\Actions\SaveCurriculumMappingAction;
use App\Modules\Academics\Http\Requests\AcademicWorkspaceRequest;
use App\Modules\Academics\Http\Requests\CreateCurriculumRequest;
use App\Modules\Academics\Http\Requests\CurriculumScheduleRequest;
use App\Modules\Academics\Http\Requests\CurriculumSectionRequest;
use App\Modules\Academics\Http\Requests\CurriculumSubjectRequest;
use App\Modules\Academics\Http\Requests\DeleteCurriculumDraftRequest;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Models\CurriculumSectionTemplate;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Support\CurrentAcademicYear;
use App\Modules\Academics\Support\OfficialClassProgramTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CurriculumController
{
    public function index(AcademicWorkspaceRequest $request, CurrentAcademicYear $currentYear): View
    {
        Gate::authorize('viewAny', Curriculum::class);

        $years = AcademicYear::query()->orderByDesc('starts_on')->orderByDesc('id')->get();
        $curricula = Curriculum::query()->with(['academicYear', 'gradeSubjects.gradeLevel', 'gradeSubjects.subject', 'sectionTemplates.gradeLevel', 'sectionTemplates.schedules.subject'])
            ->orderByDesc('academic_year_id')->orderByDesc('version')->get();
        $requested = $curricula->firstWhere('public_id', $request->query('curriculum'));
        $year = $requested?->academicYear ?? $currentYear->resolve($years, $request->validated('year'));
        $yearCurricula = $year === null ? collect() : $curricula->where('academic_year_id', $year->id)->values();
        $selected = $requested ?? $yearCurricula->first();
        $grades = GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get();
        $grade = $grades->firstWhere('public_id', $request->validated('grade'));
        $published = $curricula->where('status', 'published');
        $previousPublished = $year === null ? $published->first() : $published
            ->filter(fn (Curriculum $item): bool => $item->academicYear->starts_on->lt($year->starts_on))
            ->sortByDesc(fn (Curriculum $item): string => $item->academicYear->starts_on->format('Y-m-d'))
            ->first();

        return view('academics.admin.curricula', [
            'curricula' => $curricula,
            'yearCurricula' => $yearCurricula,
            'selected' => $selected,
            'allYears' => $years,
            'year' => $year,
            'years' => $years->whereIn('status', ['planned', 'open'])->values(),
            'grades' => $grades,
            'grade' => $grade,
            'defaultSource' => $previousPublished?->id ?? OfficialClassProgramTemplate::SOURCE_KEY,
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(CreateCurriculumRequest $request, CreateCurriculumDraftAction $create): RedirectResponse
    {
        Gate::authorize('create', Curriculum::class);
        $data = $request->validated();
        $useOfficialDefault = ($data['source_curriculum_id'] ?? null) === OfficialClassProgramTemplate::SOURCE_KEY;
        $source = isset($data['source_curriculum_id']) && ! $useOfficialDefault
            ? Curriculum::query()->findOrFail($data['source_curriculum_id'])
            : null;
        $curriculum = $create->execute(AcademicYear::query()->findOrFail($data['academic_year_id']), $data['name'], $source, $request->user(), $useOfficialDefault);

        return redirect()->route('academics.admin.curricula.index', ['curriculum' => $curriculum->public_id])->with('status', 'Curriculum draft created. Review it before publishing.');
    }

    public function subject(CurriculumSubjectRequest $request, Curriculum $curriculum, SaveCurriculumMappingAction $save): RedirectResponse
    {
        Gate::authorize('update', $curriculum);
        $save->subject($curriculum, $request->validated(), $request->user());

        return back()->with('status', 'Grade subject added.');
    }

    public function section(CurriculumSectionRequest $request, Curriculum $curriculum, SaveCurriculumMappingAction $save): RedirectResponse
    {
        Gate::authorize('update', $curriculum);
        $save->section($curriculum, $request->validated(), $request->user());

        return back()->with('status', 'Section template saved.');
    }

    public function schedule(CurriculumScheduleRequest $request, Curriculum $curriculum, CurriculumSectionTemplate $template, SaveCurriculumMappingAction $save): RedirectResponse
    {
        Gate::authorize('update', $curriculum);
        $save->schedule($curriculum, $template, $request->validated(), $request->user());

        return back()->with('status', 'Schedule template saved.');
    }

    public function publish(Curriculum $curriculum, PublishCurriculumAction $publish): RedirectResponse
    {
        Gate::authorize('publish', $curriculum);
        $publish->execute($curriculum, request()->user());

        return redirect()->route('academics.admin.index', ['year' => $curriculum->academicYear->public_id])
            ->with('status', 'Curriculum published. Its sections and subjects are ready below; assign advisers and subject teachers next.');
    }

    public function destroy(DeleteCurriculumDraftRequest $request, Curriculum $curriculum, DeleteCurriculumDraftAction $delete): RedirectResponse
    {
        $request->validated();
        $delete->execute($curriculum, $request->user());

        return redirect()->route('academics.admin.curricula.index')->with('status', 'Curriculum draft deleted. Published curricula and school-year records were kept.');
    }

    public function removeSubject(Curriculum $curriculum, int $mappingId, SaveCurriculumMappingAction $save): RedirectResponse
    {
        Gate::authorize('update', $curriculum);
        $save->removeSubject($curriculum, $mappingId, request()->user());

        return back()->with('status', 'Subject removed from the draft.');
    }

    public function removeSection(Curriculum $curriculum, CurriculumSectionTemplate $template, SaveCurriculumMappingAction $save): RedirectResponse
    {
        Gate::authorize('update', $curriculum);
        $save->removeSection($curriculum, $template, request()->user());

        return back()->with('status', 'Section removed from the draft.');
    }
}
