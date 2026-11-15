<?php

namespace App\Modules\People\Queries;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\Shared\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * D-059: the recorded changes to one learner's records, newest first, for the Registrar's student record:
 * the learner profile, their enrollments, requirement checklists, section placements, family links, and the
 * parent/guardian profiles connected to them. Only change events are listed; reads (record views, document
 * previews/downloads) and other modules' events (support, grading, billing) stay out.
 */
class StudentChangeHistoryQuery
{
    private const ACTION_PREFIXES = ['enrollment.', 'people.', 'requirements.', 'section_assignment.'];

    /** @return Collection<int, AuditLog> */
    public function for(StudentProfile $student, int $limit = 50): Collection
    {
        $enrollmentIds = Enrollment::query()->where('student_id', $student->id)->pluck('id');
        $guardianIds = GuardianStudent::query()->where('student_id', $student->id)->pluck('guardian_id')
            ->merge(Enrollment::query()->whereKey($enrollmentIds)->pluck('applicant_guardian_id'))->filter()->unique();
        $subjects = [
            StudentProfile::class => [$student->id],
            Enrollment::class => $enrollmentIds,
            EnrollmentRequirement::class => EnrollmentRequirement::query()->whereIn('enrollment_id', $enrollmentIds)->pluck('id'),
            SectionAssignment::class => SectionAssignment::query()->whereIn('enrollment_id', $enrollmentIds)->pluck('id'),
            GuardianStudent::class => GuardianStudent::query()->where('student_id', $student->id)->pluck('id'),
            GuardianProfile::class => $guardianIds,
        ];

        return AuditLog::query()->with('actor')
            ->where(function (Builder $query) use ($subjects): void {
                foreach ($subjects as $type => $ids) {
                    $ids = collect($ids)->values()->all();
                    if ($ids !== []) {
                        $query->orWhere(fn (Builder $match) => $match->where('subject_type', $type)->whereIn('subject_id', $ids));
                    }
                }
            })
            ->where(function (Builder $query): void {
                foreach (self::ACTION_PREFIXES as $prefix) {
                    $query->orWhere('action', 'like', $prefix.'%');
                }
            })
            ->where('action', 'not like', '%viewed')
            ->where('action', 'not like', 'requirements.document.%')
            ->latest('occurred_at')->latest('id')
            ->limit($limit)->get();
    }
}
