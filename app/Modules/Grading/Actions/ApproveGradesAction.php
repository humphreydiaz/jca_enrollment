<?php

namespace App\Modules\Grading\Actions;

use App\Models\User;
use App\Modules\Grading\Models\StudentSubjectGrade;
use Illuminate\Validation\ValidationException;

/** Approves a reviewed list of submitted final grades, one audited finalization per grade. */
class ApproveGradesAction
{
    public function __construct(private readonly FinalizeGradeAction $finalize) {}

    /**
     * @param  list<string>  $publicIds
     * @return array{approved: int, skipped: int}
     */
    public function execute(array $publicIds, User $actor): array
    {
        $approved = 0;
        $skipped = 0;
        foreach (StudentSubjectGrade::query()->whereIn('public_id', $publicIds)->get() as $grade) {
            try {
                $this->finalize->execute($grade, $actor);
                $approved++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return ['approved' => $approved, 'skipped' => $skipped + max(0, count($publicIds) - $approved - $skipped)];
    }
}
