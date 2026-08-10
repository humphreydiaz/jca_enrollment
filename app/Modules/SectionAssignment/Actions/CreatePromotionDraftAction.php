<?php

namespace App\Modules\SectionAssignment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentStatusHistory;
use App\Modules\Requirements\Actions\PreparePromotedEnrollmentRequirementsAction;
use App\Modules\SectionAssignment\Models\PromotionRecommendation;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePromotionDraftAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PreparePromotedEnrollmentRequirementsAction $prepareRequirements,
    ) {}

    public function execute(PromotionRecommendation $recommendation, User $actor): Enrollment
    {
        return DB::transaction(function () use ($recommendation, $actor): Enrollment {
            $record = PromotionRecommendation::query()->lockForUpdate()->findOrFail($recommendation->id);
            $source = Enrollment::query()->lockForUpdate()->findOrFail($record->source_enrollment_id);
            if ($record->eligibility_status !== 'eligible' || $record->target_academic_year_id === null || $record->target_grade_level_id === null || $source->status !== 'enrolled') {
                throw ValidationException::withMessages(['promotion' => 'This recommendation is not eligible for draft creation.']);
            }
            if ($record->created_enrollment_id !== null || Enrollment::query()->where('previous_enrollment_id', $source->id)->exists()) {
                throw ValidationException::withMessages(['promotion' => 'A promotion draft already exists for this enrollment.']);
            }
            if (Enrollment::query()->where('student_id', $source->student_id)->where('academic_year_id', $record->target_academic_year_id)->whereNotIn('status', ['rejected', 'withdrawn', 'cancelled'])->exists()) {
                throw ValidationException::withMessages(['promotion' => 'The student already has an active enrollment for the target school year.']);
            }

            $draft = Enrollment::query()->create([
                'application_number' => 'JCA-PROMO-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                'student_id' => $source->student_id,
                'academic_year_id' => $record->target_academic_year_id,
                'grade_level_id' => $record->target_grade_level_id,
                'applicant_guardian_id' => $source->applicant_guardian_id,
                'student_category' => $source->student_category,
                'guardian_relationship' => $source->guardian_relationship,
                'status' => 'draft',
                'active_enrollment_key' => $source->student_id.':'.$record->target_academic_year_id,
                'contact_verified_at' => $source->contact_verified_at,
                'duplicate_review_status' => 'clear',
                'previous_enrollment_id' => $source->id,
            ]);
            $this->prepareRequirements->execute($source, $draft, $actor);
            EnrollmentStatusHistory::query()->create(['enrollment_id' => $draft->id, 'from_status' => null, 'to_status' => 'draft', 'reason' => 'Created after Registrar/Admin promotion review.', 'changed_by' => $actor->id, 'metadata' => ['source_enrollment_public_id' => $source->public_id], 'changed_at' => now()]);
            $record->update(['created_enrollment_id' => $draft->id, 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $this->audit->record($actor, 'promotion.draft.created', $draft, ['source_enrollment_public_id' => $source->public_id]);

            return $draft;
        });
    }
}
