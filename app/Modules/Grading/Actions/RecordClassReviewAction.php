<?php

namespace App\Modules\Grading\Actions;

use App\Models\User;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Support\GradeFingerprint;
use App\Modules\Shared\Audit\AuditRecorder;

/**
 * "Review all": turns what the reviewer saw on the Waiting list into review snapshots.
 * Only grades that are still waiting and unchanged since the page was shown are kept, so
 * approving later can never include a value the reviewer did not see (D-045).
 */
class RecordClassReviewAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, list<string>>  $seen  class key => ["gradePublicId:fingerprint", ...]
     * @return array{snapshots: array<string, array<string, string>>, grades: int, stale: int}
     */
    public function execute(array $seen, User $actor): array
    {
        $pairs = collect($seen)->map(fn (array $items) => collect($items)->mapWithKeys(function (string $item): array {
            [$publicId, $fingerprint] = explode(':', $item, 2);

            return [$publicId => $fingerprint];
        }));
        $current = StudentSubjectGrade::query()
            ->whereIn('public_id', $pairs->flatMap(fn ($items) => $items->keys())->unique()->values())
            ->where('status', 'submitted')
            ->whereDoesntHave('corrections', fn ($query) => $query->where('status', 'pending'))
            ->get()->keyBy('public_id');

        $snapshots = [];
        $kept = 0;
        $stale = 0;
        foreach ($pairs as $classKey => $items) {
            foreach ($items as $publicId => $fingerprint) {
                $grade = $current->get($publicId);
                if ($grade !== null && hash_equals(GradeFingerprint::of($grade), $fingerprint)) {
                    $snapshots[$classKey][$publicId] = $fingerprint;
                    $kept++;
                } else {
                    $stale++;
                }
            }
        }

        $this->audit->record($actor, 'grading.admin.classes_reviewed', null, ['classes' => count($snapshots), 'grades' => $kept, 'stale' => $stale]);

        return ['snapshots' => $snapshots, 'grades' => $kept, 'stale' => $stale];
    }
}
