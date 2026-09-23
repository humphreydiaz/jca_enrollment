<?php

namespace App\Modules\Communications\Queries;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Support\Collection;

/**
 * Resolves which guardians should receive an announcement SMS for a given set
 * of audiences. Shared by announcement creation and by publishing an existing
 * draft so both paths target the exact same recipients.
 *
 * Each audience is a normalized array: ['type' => 'all'|'role'|'section'|'guardian'|'student',
 * 'role_code' => ?string, 'section_id' => ?int, 'guardian_id' => ?int].
 */
class AnnouncementSmsRecipients
{
    /**
     * @param list<array<string, mixed>> $audiences
     * @return Collection<int, GuardianProfile>
     */
    public function forAudiences(array $audiences): Collection
    {
        $all = false;
        $guardianIds = [];
        $sectionIds = [];
        foreach ($audiences as $audience) {
            $all = $all || ($audience['type'] ?? null) === 'all' || (($audience['type'] ?? null) === 'role' && ($audience['role_code'] ?? null) === 'guardian');
            if (($audience['type'] ?? null) === 'guardian') {
                $guardianIds[] = (int) $audience['guardian_id'];
            }
            if (($audience['type'] ?? null) === 'section') {
                $sectionIds[] = (int) $audience['section_id'];
            }
        }
        if ($all) {
            return GuardianProfile::query()->whereNotNull('mobile')->get();
        }
        if ($sectionIds !== []) {
            $enrollmentIds = SectionAssignment::query()->whereIn('section_id', $sectionIds)->where('status', 'confirmed')->whereNull('ended_at')->pluck('enrollment_id');
            $studentIds = Enrollment::query()->whereIn('id', $enrollmentIds)->pluck('student_id');
            $guardianIds = array_merge($guardianIds, GuardianStudent::query()->whereIn('student_id', $studentIds)->where('may_receive_sms', true)->whereNotNull('verified_at')->whereNull('ended_at')->pluck('guardian_id')->all());
        }

        return GuardianProfile::query()->whereIn('id', array_unique($guardianIds))->whereNotNull('mobile')->get();
    }
}
