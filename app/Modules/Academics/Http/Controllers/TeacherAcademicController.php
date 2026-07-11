<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Queries\TeacherAssignmentsQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeacherAcademicController
{
    public function __invoke(TeacherAssignmentsQuery $assignments): View
    {
        Gate::authorize('viewAny', TeachingAssignment::class);

        return view('academics.teacher.assignments', [
            'assignments' => $assignments->forUser(request()->user()),
        ]);
    }
}
