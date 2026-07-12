<?php

namespace App\Modules\StudentSupport\Actions;

use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\StudentSupport\Models\ApprovedSupportSummary;

class TeacherStudentSupportView
{
    public function __construct(
        public readonly SectionAssignment $assignment,
        public readonly ?ApprovedSupportSummary $summary,
    ) {}
}
