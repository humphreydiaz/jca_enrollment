<?php

namespace App\Modules\SectionAssignment\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Section;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\SectionAssignment\Actions\ConfirmSectionAssignmentAction;
use App\Modules\SectionAssignment\Actions\CreatePromotionDraftAction;
use App\Modules\SectionAssignment\Actions\RecommendPromotionAction;
use App\Modules\SectionAssignment\Actions\RecommendSectionAction;
use App\Modules\SectionAssignment\Actions\TransferSectionAssignmentAction;
use App\Modules\SectionAssignment\Http\Requests\ConfirmSectionAssignmentRequest;
use App\Modules\SectionAssignment\Http\Requests\RecommendPromotionRequest;
use App\Modules\SectionAssignment\Http\Requests\TransferSectionAssignmentRequest;
use App\Modules\SectionAssignment\Models\PromotionRecommendation;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SectionAssignmentController
{
    public function index(RecommendSectionAction $recommend): View
    {
        Gate::authorize('viewAny', SectionAssignment::class);
        $approved = Enrollment::query()->where('status', 'approved')->with(['student', 'academicYear', 'gradeLevel'])->orderBy('approved_at')->orderBy('id')->get();
        $recommendations = $approved->mapWithKeys(fn (Enrollment $enrollment) => [$enrollment->id => $recommend->execute($enrollment)]);

        return view('section-assignment.index', [
            'approvedEnrollments' => $approved,
            'recommendations' => $recommendations,
            'currentAssignments' => SectionAssignment::query()->where('status', 'confirmed')->whereNull('ended_at')->with(['enrollment.student', 'enrollment.academicYear', 'enrollment.gradeLevel', 'section.sectionSubjects.subject', 'events'])->latest('confirmed_at')->get(),
            'assignmentHistory' => SectionAssignment::query()->where('status', 'ended')->whereNotNull('ended_at')->with(['enrollment.student', 'section'])->latest('ended_at')->limit(50)->get(),
            'sections' => Section::query()->where('status', 'active')->with(['academicYear', 'gradeLevel'])->orderBy('academic_year_id')->orderBy('grade_level_id')->orderBy('sort_order')->orderBy('name')->get(),
            'enrolledForPromotion' => Enrollment::query()->where('status', 'enrolled')->with(['student', 'academicYear', 'gradeLevel.nextGradeLevel', 'promotionRecommendation.targetAcademicYear', 'promotionRecommendation.targetGradeLevel'])->orderByDesc('enrolled_at')->get(),
            'futureYears' => AcademicYear::query()->whereIn('status', ['planned', 'open'])->orderBy('starts_on')->get(),
        ]);
    }

    public function confirm(Enrollment $enrollment, ConfirmSectionAssignmentRequest $request, ConfirmSectionAssignmentAction $confirm): RedirectResponse
    {
        Gate::authorize('create', SectionAssignment::class);
        $data = $request->validated();
        $section = Section::query()->where('public_id', $data['section_public_id'])->firstOrFail();
        $confirm->execute($enrollment, $section, $request->user(), $request->boolean('support_suitability_confirmed'), $request->boolean('override_capacity'), $data['override_reason'] ?? null);

        return back()->with('status', 'Section confirmed and academic enrollment finalized.');
    }

    public function transfer(SectionAssignment $assignment, TransferSectionAssignmentRequest $request, TransferSectionAssignmentAction $transfer): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $data = $request->validated();
        $section = Section::query()->where('public_id', $data['section_public_id'])->firstOrFail();
        $transfer->execute($assignment, $section, $request->user(), $data['reason'], $request->boolean('support_suitability_confirmed'), $request->boolean('override_capacity'), $data['override_reason'] ?? null);

        return back()->with('status', 'Section transfer completed with history preserved.');
    }

    public function recommendPromotion(Enrollment $enrollment, RecommendPromotionRequest $request, RecommendPromotionAction $recommend): RedirectResponse
    {
        Gate::authorize('update', $enrollment);
        $year = $request->validated('target_academic_year_public_id') ? AcademicYear::query()->where('public_id', $request->validated('target_academic_year_public_id'))->firstOrFail() : null;
        $recommend->execute($enrollment, $request->user(), $year);

        return back()->with('status', 'Promotion recommendation reviewed.');
    }

    public function createPromotionDraft(PromotionRecommendation $recommendation, CreatePromotionDraftAction $create): RedirectResponse
    {
        Gate::authorize('update', $recommendation->sourceEnrollment);
        $create->execute($recommendation, request()->user());

        return back()->with('status', 'Next-school-year enrollment draft created for Registrar review.');
    }
}
