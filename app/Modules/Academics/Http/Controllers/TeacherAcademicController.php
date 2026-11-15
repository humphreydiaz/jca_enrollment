<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Modules\Academics\Http\Requests\TeacherWorkspaceRequest;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Queries\TeacherWorkspaceQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeacherAcademicController
{
    public function __invoke(TeacherWorkspaceRequest $request, TeacherWorkspaceQuery $workspace): View
    {
        Gate::authorize('viewAny', TeachingAssignment::class);

        return view('academics.teacher.assignments', $workspace->forUser($request->user(), $request->validated('year')));
    }
}
