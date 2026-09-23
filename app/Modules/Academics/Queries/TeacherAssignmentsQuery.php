<?php

namespace App\Modules\Academics\Queries;

use App\Models\User;
use App\Modules\Academics\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Collection;

class TeacherAssignmentsQuery
{
    /** @return Collection<int, TeachingAssignment> */
    public function forUser(User $user): Collection
    {
        return TeachingAssignment::query()
            ->active()
            ->forUser($user)
            ->with([
                'section.academicYear',
                'section.gradeLevel',
                'section.sectionSubjects.subject',
                'sectionSubject.subject',
            ])
            ->orderBy('section_id')
            ->get();
    }
}
