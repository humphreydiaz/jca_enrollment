<?php

namespace App\Modules\SectionAssignment\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\SectionAssignment\Actions\ConfirmSectionAssignmentAction;
use App\Modules\SectionAssignment\Actions\CreatePromotionDraftAction;
use App\Modules\SectionAssignment\Actions\RecommendPromotionAction;
use App\Modules\SectionAssignment\Actions\RecommendSectionAction;
use App\Modules\SectionAssignment\Actions\TransferSectionAssignmentAction;
use App\Modules\SectionAssignment\Http\Requests\ConfirmSectionAssignmentRequest;
use App\Modules\SectionAssignment\Http\Requests\ListSectionAssignmentsRequest;
use App\Modules\SectionAssignment\Http\Requests\RecommendPromotionRequest;
use App\Modules\SectionAssignment\Http\Requests\TransferSectionAssignmentRequest;
use App\Modules\SectionAssignment\Http\Requests\MassPromotionRequest;
use App\Modules\SectionAssignment\Models\PromotionRecommendation;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SectionAssignmentController
{
    public function index(ListSectionAssignmentsRequest $request, RecommendSectionAction $recommend): View
    {
        Gate::authorize('viewAny', SectionAssignment::class);
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);
        $yearId = isset($filters['year']) ? AcademicYear::query()->where('public_id', $filters['year'])->value('id') : null;
        $gradeId = isset($filters['grade']) ? GradeLevel::query()->where('public_id', $filters['grade'])->value('id') : null;
        $sectionId = isset($filters['section']) ? Section::query()->where('public_id', $filters['section'])->value('id') : null;

        $approvedQuery = Enrollment::query()
            ->where('status', 'approved')
            ->with(['student', 'academicYear', 'gradeLevel']);
        $this->applyEnrollmentFilters($approvedQuery, $filters, $yearId, $gradeId, $sectionId);
        $approved = $approvedQuery
            ->orderBy('approved_at')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'awaiting_page')
            ->withQueryString()
            ->fragment('awaiting-placement');
        $recommendations = $approved->getCollection()->mapWithKeys(
            fn (Enrollment $enrollment): array => [$enrollment->id => $recommend->execute($enrollment)],
        );

        $currentQuery = SectionAssignment::query()
            ->where('status', 'confirmed')
            ->whereNull('ended_at')
            ->with(['enrollment.student', 'enrollment.academicYear', 'enrollment.gradeLevel', 'section']);
        $this->applyAssignmentFilters($currentQuery, $filters, $yearId, $gradeId, $sectionId);
        $currentAssignments = $currentQuery
            ->latest('confirmed_at')
            ->paginate($perPage, ['*'], 'assignments_page')
            ->withQueryString()
            ->fragment('current-assignments');

        $historyQuery = SectionAssignment::query()
            ->where('status', 'ended')
            ->whereNotNull('ended_at')
            ->with(['enrollment.student', 'enrollment.academicYear', 'enrollment.gradeLevel', 'section']);
        $this->applyAssignmentFilters($historyQuery, $filters, $yearId, $gradeId, $sectionId);
        $assignmentHistory = $historyQuery
            ->latest('ended_at')
            ->paginate($perPage, ['*'], 'history_page')
            ->withQueryString()
            ->fragment('transfer-history');

        $promotionQuery = Enrollment::query()
            ->where('status', 'enrolled')
            ->with(['student', 'academicYear', 'gradeLevel.nextGradeLevel', 'promotionRecommendation.targetAcademicYear', 'promotionRecommendation.targetGradeLevel']);
        $this->applyEnrollmentFilters($promotionQuery, $filters, $yearId, $gradeId, $sectionId);
        $this->applyPromotionStatusFilter($promotionQuery, $filters['promotion_status'] ?? null);
        $enrolledForPromotion = $promotionQuery
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'promotion_page')
            ->withQueryString()
            ->fragment('promotion-review');

        return view('section-assignment.index', [
            'approvedEnrollments' => $approved,
            'recommendations' => $recommendations,
            'currentAssignments' => $currentAssignments,
            'assignmentHistory' => $assignmentHistory,
            'sections' => Section::query()
                ->where('status', 'active')
                ->with(['academicYear', 'gradeLevel'])
                ->withCount(['sectionAssignments as confirmed_count' => fn (Builder $query) => $query->where('status', 'confirmed')->whereNull('ended_at')])
                ->orderBy('academic_year_id')
                ->orderBy('grade_level_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'enrolledForPromotion' => $enrolledForPromotion,
            'futureYears' => AcademicYear::query()->whereIn('status', ['planned', 'open'])->orderBy('starts_on')->get(),
            'filterYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'filterGrades' => GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get(),
            'filters' => $filters,
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function applyEnrollmentFilters(Builder $query, array $filters, ?int $yearId, ?int $gradeId, ?int $sectionId = null): void
    {
        if ($yearId !== null) {
            $query->where('academic_year_id', $yearId);
        }
        if ($gradeId !== null) {
            $query->where('grade_level_id', $gradeId);
        }
        if ($sectionId !== null) {
            $query->whereHas('sectionAssignments', fn (Builder $assignment) => $assignment
                ->where('status', 'confirmed')
                ->whereNull('ended_at')
                ->where('section_id', $sectionId));
        }
        if (filled($filters['search'] ?? null)) {
            $tokens = collect(preg_split('/\s+/', $filters['search']) ?: [])->filter()->take(5);
            foreach ($tokens as $token) {
                $query->where(function (Builder $builder) use ($token): void {
                    $like = '%'.$token.'%';
                    $builder->where('application_number', 'like', $like)
                        ->orWhereHas('student', function (Builder $student) use ($like): void {
                            $student->where('student_number', 'like', $like)
                                ->orWhere('first_name', 'like', $like)
                                ->orWhere('middle_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like);
                        });
                });
            }
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyAssignmentFilters(Builder $query, array $filters, ?int $yearId, ?int $gradeId, ?int $sectionId = null): void
    {
        if ($sectionId !== null) {
            $query->where('section_id', $sectionId);
        }
        $nonSearchFilters = $filters;
        unset($nonSearchFilters['search']);
        $query->whereHas('enrollment', function (Builder $enrollment) use ($nonSearchFilters, $yearId, $gradeId): void {
            $this->applyEnrollmentFilters($enrollment, $nonSearchFilters, $yearId, $gradeId);
        });

        if (filled($filters['search'] ?? null)) {
            $tokens = collect(preg_split('/\s+/', $filters['search']) ?: [])->filter()->take(5);
            foreach ($tokens as $token) {
                $query->where(function (Builder $builder) use ($token): void {
                    $like = '%'.$token.'%';
                    $builder->whereHas('section', fn (Builder $section) => $section->where('name', 'like', $like))
                        ->orWhereHas('enrollment', function (Builder $enrollment) use ($like): void {
                            $enrollment->where('application_number', 'like', $like)
                                ->orWhereHas('student', function (Builder $student) use ($like): void {
                                    $student->where('student_number', 'like', $like)
                                        ->orWhere('first_name', 'like', $like)
                                        ->orWhere('middle_name', 'like', $like)
                                        ->orWhere('last_name', 'like', $like);
                                });
                        });
                });
            }
        }
    }

    private function applyPromotionStatusFilter(Builder $query, ?string $status): void
    {
        match ($status) {
            'unreviewed' => $query->whereDoesntHave('promotionRecommendation'),
            'eligible' => $query->whereHas('promotionRecommendation', fn (Builder $recommendation) => $recommendation->where('eligibility_status', 'eligible')->whereNull('created_enrollment_id')),
            'ineligible' => $query->whereHas('promotionRecommendation', fn (Builder $recommendation) => $recommendation->where('eligibility_status', 'ineligible')),
            'draft_created' => $query->whereHas('promotionRecommendation', fn (Builder $recommendation) => $recommendation->whereNotNull('created_enrollment_id')),
            default => null,
        };
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

    public function massRecommendPromotion(MassPromotionRequest $request, RecommendPromotionAction $recommend): RedirectResponse
    {
        Gate::authorize('viewAny', SectionAssignment::class);
        $data = $request->validated();
        $targetYear = isset($data['target_academic_year_public_id'])
            ? AcademicYear::query()->where('public_id', $data['target_academic_year_public_id'])->first()
            : null;

        $eligible = 0;
        $ineligible = 0;
        $skipped = 0;

        foreach ($data['enrollment_public_ids'] as $publicId) {
            $enrollment = Enrollment::query()->where('public_id', $publicId)->first();
            if (! $enrollment || $enrollment->status !== 'enrolled') {
                $skipped++;

                continue;
            }
            $existing = PromotionRecommendation::query()->where('source_enrollment_id', $enrollment->id)->first();
            if ($existing?->created_enrollment_id !== null) {
                $skipped++;

                continue;
            }

            try {
                $result = $recommend->execute($enrollment, $request->user(), $targetYear);
                if ($result->eligibility_status === 'eligible') {
                    $eligible++;
                } else {
                    $ineligible++;
                }
            } catch (\Throwable) {
                $skipped++;
            }
        }

        $total = $eligible + $ineligible + $skipped;

        return back()->with('status', "Reviewed {$total} students: {$eligible} eligible, {$ineligible} ineligible, {$skipped} skipped.");
    }

    public function massCreatePromotionDrafts(MassPromotionRequest $request, CreatePromotionDraftAction $create): RedirectResponse
    {
        Gate::authorize('viewAny', SectionAssignment::class);
        $data = $request->validated();

        $created = 0;
        $skipped = 0;

        foreach ($data['enrollment_public_ids'] as $publicId) {
            $enrollment = Enrollment::query()->where('public_id', $publicId)->first();
            if (! $enrollment) {
                $skipped++;

                continue;
            }
            $recommendation = PromotionRecommendation::query()
                ->where('source_enrollment_id', $enrollment->id)
                ->where('eligibility_status', 'eligible')
                ->whereNull('created_enrollment_id')
                ->first();
            if (! $recommendation) {
                $skipped++;

                continue;
            }

            try {
                $create->execute($recommendation, $request->user());
                $created++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return back()->with('status', "Created {$created} promotion drafts. {$skipped} skipped (already created or not eligible).");
    }
}
