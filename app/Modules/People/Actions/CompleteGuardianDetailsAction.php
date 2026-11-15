<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\Shared\Audit\AuditValueMask;
use Illuminate\Support\Facades\DB;

/**
 * Fills in (or corrects) the parent/guardian details the family enters on the enrollment
 * application. Walk-in access no longer collects them at the counter (D-047), so the
 * guardian starts with placeholder details and the verified link created in person.
 */
class CompleteGuardianDetailsAction
{
    /** Placeholder first name used until the family completes the application. */
    public const PENDING_FIRST_NAME = 'Parent/Guardian';

    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $data */
    public function execute(GuardianProfile $guardian, StudentProfile $student, array $data, User $actor): GuardianProfile
    {
        return DB::transaction(function () use ($guardian, $student, $data, $actor): GuardianProfile {
            $guardian = GuardianProfile::query()->lockForUpdate()->findOrFail($guardian->id);
            $before = $guardian->only(['first_name', 'middle_name', 'last_name', 'extension_name', 'mobile', 'email']);
            $guardian->fill([
                'first_name' => trim((string) $data['guardian_first_name']),
                'middle_name' => filled($data['guardian_middle_name'] ?? null) ? trim((string) $data['guardian_middle_name']) : null,
                'last_name' => trim((string) $data['guardian_last_name']),
                'extension_name' => filled($data['guardian_extension_name'] ?? null) ? trim((string) $data['guardian_extension_name']) : null,
                'mobile' => ContactNormalizer::mobile($data['guardian_mobile'] ?? null),
                'email' => ContactNormalizer::email($data['guardian_email'] ?? null),
            ]);
            $after = $guardian->only(array_keys($before));
            $guardian->save();

            GuardianStudent::query()
                ->where('guardian_id', $guardian->id)
                ->where('student_id', $student->id)
                ->whereNull('ended_at')
                ->update(['relationship_type' => $data['guardian_relationship']]);

            // D-059: every changed field is named; contact numbers and emails also keep masked old/new values.
            $this->audit->recordChanges($actor, 'people.guardian.details_completed', $guardian, $before, $after, [
                'mobile' => AuditValueMask::MOBILE,
                'email' => AuditValueMask::EMAIL,
            ]);

            return $guardian;
        });
    }
}
