<?php

namespace App\Modules\Grading\Policies;

use App\Models\User;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Support\NumericGradeLevels;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Support\StudentAccountIdentityMatcher;
use App\Modules\SectionAssignment\Models\SectionAssignment;

class StudentSubjectGradePolicy
{
    /** The grade approval queue: the Registrar approves final grades; the Admin keeps the same authority. */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']) && $user->isActive();
    }

    public function viewTeacherRoster(User $user): bool
    {
        return $user->hasRole('teacher') && $user->isActive();
    }

    public function viewFamilyGrades(User $user): bool
    {
        return $user->hasAnyRole(['guardian', 'student']) && $user->isActive();
    }

    public function create(User $user, TeachingAssignment $assignment, Enrollment $enrollment): bool
    {
        if (! $user->hasRole('teacher') || ! $user->isActive()) {
            return false;
        }

        $assignment = TeachingAssignment::query()->active()->forUser($user)
            ->with(['section.gradeLevel', 'sectionSubject'])
            ->whereKey($assignment->id)->first();

        if ($assignment === null || $assignment->assignment_type !== 'subject_teacher'
            || $assignment->sectionSubject === null
            || $assignment->sectionSubject->section_id !== $assignment->section_id
            || ! NumericGradeLevels::includes($assignment->section->gradeLevel)
            || $enrollment->status !== 'enrolled'
            || $enrollment->academic_year_id !== $assignment->section->academic_year_id
            || $enrollment->grade_level_id !== $assignment->section->grade_level_id) {
            return false;
        }

        return SectionAssignment::query()->where('enrollment_id', $enrollment->id)
            ->where('section_id', $assignment->section_id)
            ->where('status', 'confirmed')->whereNull('ended_at')->exists();
    }

    public function update(User $user, StudentSubjectGrade $grade, TeachingAssignment $assignment): bool
    {
        return $grade->subject_id === $assignment->sectionSubject?->subject_id
            && $this->create($user, $assignment, $grade->enrollment);
    }

    public function view(User $user, StudentSubjectGrade $grade): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'registrar'])) {
            return true;
        }

        if ($user->hasRole('teacher')) {
            return TeachingAssignment::query()->active()->forUser($user)
                ->whereHas('sectionSubject', fn ($query) => $query->where('subject_id', $grade->subject_id))
                ->whereHas('section', fn ($query) => $query->whereHas('sectionAssignments', fn ($assigned) => $assigned
                    ->where('enrollment_id', $grade->enrollment_id)->where('status', 'confirmed')->whereNull('ended_at')))
                ->exists();
        }

        if ($grade->status !== 'finalized') {
            return false;
        }

        if ($user->hasRole('student') && $user->studentProfile?->id === $grade->enrollment->student_id) {
            return app(StudentAccountIdentityMatcher::class)->matches($user->studentProfile, $user);
        }

        return $user->hasRole('guardian') && $user->guardianProfile !== null
            && GuardianStudent::query()->where('guardian_id', $user->guardianProfile->id)
                ->where('student_id', $grade->enrollment->student_id)
                ->whereNotNull('verified_at')->whereNull('ended_at')
                ->where('may_manage_enrollment', true)->exists();
    }

    /** Approving (finalizing) a final grade, or deciding a teacher's reopen request. */
    public function finalize(User $user, StudentSubjectGrade $grade): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']) && $user->isActive();
    }
}
