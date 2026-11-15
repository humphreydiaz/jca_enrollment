<?php

namespace App\Modules\Grading\Http\Controllers;

use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Support\StudentAccountIdentityMatcher;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FamilyGradeController
{
    public function __invoke(Request $request, StudentAccountIdentityMatcher $identity, AuditRecorder $audit): View
    {
        Gate::authorize('viewFamilyGrades', StudentSubjectGrade::class);
        $user = $request->user();
        $studentIds = collect();
        if ($user->hasRole('student') && $user->studentProfile !== null && $identity->matches($user->studentProfile, $user)) {
            $studentIds->push($user->studentProfile->id);
        }
        if ($user->hasRole('guardian') && $user->guardianProfile !== null) {
            $studentIds = $studentIds->merge(GuardianStudent::query()
                ->where('guardian_id', $user->guardianProfile->id)
                ->whereNotNull('verified_at')->whereNull('ended_at')
                ->where('may_manage_enrollment', true)->pluck('student_id'));
        }

        $grades = StudentSubjectGrade::query()->where('status', 'finalized')
            ->whereHas('enrollment', fn ($query) => $query->whereIn('student_id', $studentIds->unique()->all()))
            ->with(['enrollment.student', 'enrollment.academicYear', 'enrollment.gradeLevel', 'teachingAssignment.staffProfile.user'])
            ->latest('finalized_at')->get()->filter(fn ($grade) => Gate::forUser($user)->allows('view', $grade));
        $audit->record($user, 'grading.family.viewed');

        return view('grading.family-index', compact('grades'));
    }
}
