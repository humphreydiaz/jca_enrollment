<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\TeachingAssignment;
use Illuminate\Validation\ValidationException;

class CreateTeachingAssignmentAction
{
    public function __construct(private readonly SaveAcademicCatalogAction $save) {}

    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes, User $actor): TeachingAssignment
    {
        if ($attributes['assignment_type'] === 'adviser') {
            $attributes['section_subject_id'] = null;
        }

        if (TeachingAssignment::query()
            ->where('staff_profile_id', $attributes['staff_profile_id'])
            ->where('section_id', $attributes['section_id'])
            ->where('assignment_type', $attributes['assignment_type'])
            ->when($attributes['assignment_type'] === 'subject_teacher', fn ($query) => $query->where('section_subject_id', $attributes['section_subject_id']))
            ->exists()) {
            throw ValidationException::withMessages(['staff_profile_id' => 'That teaching assignment already exists.']);
        }

        /** @var TeachingAssignment $assignment */
        $assignment = $this->save->execute(new TeachingAssignment, $attributes, $actor, 'academics.teaching_assignment.created');

        return $assignment;
    }
}
