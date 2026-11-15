<?php

namespace App\Modules\SectionAssignment\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Queries\PromotionGradeReadinessQuery;
use App\Modules\SectionAssignment\Models\PromotionRecommendation;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecommendPromotionAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PromotionGradeReadinessQuery $grades,
    ) {}

    public function execute(Enrollment $source, User $actor, ?AcademicYear $targetYear = null): PromotionRecommendation
    {
        return DB::transaction(function () use ($source, $actor, $targetYear): PromotionRecommendation {
            $enrollment = Enrollment::query()->with(['academicYear', 'gradeLevel.nextGradeLevel'])->lockForUpdate()->findOrFail($source->id);
            if ($enrollment->status !== 'enrolled') {
                throw ValidationException::withMessages(['enrollment' => 'Only an academically enrolled record can be reviewed for promotion.']);
            }
            $existing = PromotionRecommendation::query()->where('source_enrollment_id', $enrollment->id)->lockForUpdate()->first();
            if ($existing?->created_enrollment_id !== null) {
                throw ValidationException::withMessages(['promotion' => 'The promotion recommendation is locked because its draft already exists.']);
            }

            $nextGrade = $enrollment->gradeLevel->nextGradeLevel;
            if ($nextGrade === null || ! $nextGrade->is_active) {
                return $this->save($enrollment, $actor, null, null, 'ineligible', 'No active next grade is configured; this may be a terminal grade.');
            }

            $year = $targetYear ?? AcademicYear::query()
                ->whereIn('status', ['planned', 'open'])
                ->whereDate('starts_on', '>', $enrollment->academicYear->ends_on)
                ->orderBy('starts_on')
                ->orderBy('id')
                ->first();
            if ($year === null) {
                return $this->save($enrollment, $actor, null, $nextGrade->id, 'ineligible', 'No future school year is configured.');
            }
            if (! in_array($year->status, ['planned', 'open'], true) || ! $year->starts_on->isAfter($enrollment->academicYear->ends_on)) {
                throw ValidationException::withMessages(['target_academic_year_id' => 'Select a planned or open school year after the source school year.']);
            }

            // Grade-based eligibility: every current subject needs a complete final grade that the
            // Registrar (or Admin) approved. Preschool levels have no numeric grades (D-038, D-043).
            $readiness = $this->grades->forEnrollment($enrollment);
            if (! $readiness->isReady()) {
                return $this->save($enrollment, $actor, $year->id, $nextGrade->id, 'grades_pending', $readiness->summary());
            }

            return $this->save($enrollment, $actor, $year->id, $nextGrade->id, 'eligible', $readiness->summary().' Next grade and future school year are available.');
        });
    }

    private function save(Enrollment $source, User $actor, ?int $yearId, ?int $gradeId, string $status, string $reason): PromotionRecommendation
    {
        $recommendation = PromotionRecommendation::query()->updateOrCreate(
            ['source_enrollment_id' => $source->id],
            ['target_academic_year_id' => $yearId, 'target_grade_level_id' => $gradeId, 'eligibility_status' => $status, 'reason' => $reason, 'generated_by' => $actor->id, 'generated_at' => now()],
        );
        $this->audit->record($actor, 'promotion.recommended', $recommendation, ['eligibility' => $status]);

        return $recommendation;
    }
}
