<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\SiblingDiscountConfirmation;
use App\Modules\Billing\Queries\SiblingDiscountQuery;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** D-067: the Registrar (or Admin) confirms a learner's sibling discount eligibility for one school year. */
class ConfirmSiblingDiscountAction
{
    public function __construct(private readonly SiblingDiscountQuery $siblings, private readonly AuditRecorder $audit) {}

    public function execute(Enrollment $enrollment, User $actor): SiblingDiscountConfirmation
    {
        abort_unless($actor->hasAnyRole(['admin', 'registrar']), 403);

        return DB::transaction(function () use ($enrollment, $actor): SiblingDiscountConfirmation {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            if (in_array($locked->status, Enrollment::TERMINAL_STATUSES, true) || $locked->status === 'draft') {
                throw ValidationException::withMessages(['sibling_discount' => 'The sibling discount can only be confirmed for a current, submitted enrollment.']);
            }
            if (SiblingDiscountConfirmation::query()->where('active_key', $locked->id)->exists()) {
                throw ValidationException::withMessages(['sibling_discount' => 'The sibling discount is already confirmed for this school year.']);
            }
            $siblings = $this->siblings->forEnrollments(collect([$locked]))[$locked->id]['siblings'] ?? [];
            if ($siblings === []) {
                throw ValidationException::withMessages(['sibling_discount' => 'No sibling with the same verified parent/guardian is enrolled this school year.']);
            }

            $confirmation = SiblingDiscountConfirmation::query()->create([
                'enrollment_id' => $locked->id,
                'active_key' => $locked->id,
                'sibling_summary' => mb_substr(implode('; ', $siblings), 0, 500),
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);
            $this->audit->record($actor, 'billing.sibling_discount.confirmed', $confirmation, ['enrollment_public_id' => $locked->public_id, 'sibling_count' => count($siblings)]);

            return $confirmation;
        });
    }
}
