<?php

namespace App\Modules\Academics\Policies;

use App\Models\User;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeachingAssignment;

class SectionPolicy
{
    use ManagesAcademicCatalog;

    public function view(User $user, Section $section): bool
    {
        return $this->viewAny($user) || TeachingAssignment::query()
            ->active()
            ->forUser($user)
            ->where('section_id', $section->id)
            ->exists();
    }
}
