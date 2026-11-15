<?php

namespace App\Modules\Grading\Actions;

use App\Models\User;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Support\GradeFingerprint;
use Illuminate\Validation\ValidationException;

/**
 * Approves whole classes the reviewer has opened. Only the grades shown at review time
 * are approved, and only if they are unchanged since; anything submitted or edited
 * afterwards stays waiting for the next review. Each grade is finalized (and audited)
 * through FinalizeGradeAction.
 */
class ApproveReviewedClassesAction
{
    public function __construct(private readonly FinalizeGradeAction $finalize) {}

    /**
     * @param  list<string>  $classKeys
     * @param  array<string, array<string, string>>  $snapshots  class key => [grade public id => fingerprint]
     * @return array{approved: int, classes: int, changed: int, notReviewed: int, consumedKeys: list<string>}
     */
    public function execute(array $classKeys, array $snapshots, User $actor): array
    {
        $result = ['approved' => 0, 'classes' => 0, 'changed' => 0, 'notReviewed' => 0, 'consumedKeys' => []];

        foreach (array_unique($classKeys) as $key) {
            $snapshot = $snapshots[$key] ?? null;
            if (! is_array($snapshot) || $snapshot === []) {
                $result['notReviewed']++;

                continue;
            }

            $approvedHere = 0;
            $grades = StudentSubjectGrade::query()->whereIn('public_id', array_keys($snapshot))->get();
            $result['changed'] += count($snapshot) - $grades->count();
            foreach ($grades as $grade) {
                if (! hash_equals($snapshot[$grade->public_id], GradeFingerprint::of($grade))) {
                    $result['changed']++;

                    continue;
                }
                try {
                    $this->finalize->execute($grade, $actor);
                    $approvedHere++;
                } catch (ValidationException) {
                    $result['changed']++;
                }
            }

            $result['approved'] += $approvedHere;
            $result['consumedKeys'][] = $key;
            if ($approvedHere > 0) {
                $result['classes']++;
            }
        }

        return $result;
    }
}
