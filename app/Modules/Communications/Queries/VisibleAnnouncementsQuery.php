<?php

namespace App\Modules\Communications\Queries;

use App\Models\User;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Communications\Models\Announcement;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Database\Eloquent\Builder;

class VisibleAnnouncementsQuery
{
    /** @return Builder<Announcement> */
    public function forUser(User $user): Builder
    {
        $query = Announcement::query()->with('audiences')->where('status', 'published')
            ->where(fn (Builder $dates) => $dates->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $dates) => $dates->whereNull('publish_until')->orWhere('publish_until', '>=', now()));
        if ($user->hasRole('admin')) {
            return $query;
        }

        $roles = $user->roles()->pluck('code')->all();
        $guardianId = $user->guardianProfile?->id;
        $studentId = $user->studentProfile?->id;
        $linkedStudentIds = $guardianId === null ? [] : GuardianStudent::query()->where('guardian_id', $guardianId)->whereNull('ended_at')->whereNotNull('verified_at')->pluck('student_id')->all();
        $sectionIds = array_merge(
            TeachingAssignment::query()->active()->forUser($user)->pluck('section_id')->all(),
            SectionAssignment::query()->where('status', 'confirmed')->whereNull('ended_at')->whereIn('enrollment_id', function ($subquery) use ($linkedStudentIds, $studentId): void {
                $subquery->select('id')->from('enrollments')->whereIn('student_id', array_filter(array_merge($linkedStudentIds, [$studentId])));
            })->pluck('section_id')->all(),
        );

        return $query->whereHas('audiences', function (Builder $audiences) use ($roles, $guardianId, $studentId, $sectionIds): void {
            $audiences->where('audience_type', 'all')
                ->orWhere(fn (Builder $scope) => $scope->where('audience_type', 'role')->whereIn('role_code', $roles))
                ->orWhere(fn (Builder $scope) => $guardianId !== null && $scope->where('audience_type', 'guardian')->where('guardian_id', $guardianId))
                ->orWhere(fn (Builder $scope) => $studentId !== null && $scope->where('audience_type', 'student')->where('student_id', $studentId))
                ->orWhere(fn (Builder $scope) => $scope->where('audience_type', 'section')->whereIn('section_id', array_unique($sectionIds)));
        });
    }
}
