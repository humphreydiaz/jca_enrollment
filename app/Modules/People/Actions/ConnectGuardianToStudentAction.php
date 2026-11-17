<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConnectGuardianToStudentAction
{
    public function __construct(
        private readonly LinkGuardianStudentAction $link,
        private readonly VerifyGuardianStudentLinkAction $verify,
    ) {}

    public function execute(
        GuardianProfile $guardian,
        StudentProfile $student,
        string $relationship,
        User $actor
    ): GuardianStudent {

        return DB::transaction(function () use (
            $guardian,
            $student,
            $relationship,
            $actor
        ): GuardianStudent {

            // Check whether the parent has a login account.
            if ($guardian->user_id === null) {
                throw ValidationException::withMessages([
                    'guardian' => 'This parent/guardian does not have a login account yet.',
                ]);
            }

            // Prevent duplicate active parent-student links.
            $existing = GuardianStudent::query()
                ->where('guardian_id', $guardian->id)
                ->where('student_id', $student->id)
                ->whereNull('ended_at')
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'guardian' => $guardian->full_name .
                        ' is already linked to ' .
                        $student->full_name . '.',
                ]);
            }

            // Get the latest non-terminal enrollment.
            $enrollment = $student->enrollments()
                ->whereNotIn(
                    'status',
                    Enrollment::TERMINAL_STATUSES
                )
                ->with('gradeLevel')
                ->latest('id')
                ->first();

            // Fall back to the latest enrollment record.
            if ($enrollment === null) {
                $enrollment = $student->enrollments()
                    ->with('gradeLevel')
                    ->latest('id')
                    ->first();
            }

            $gradeCode = $enrollment?->gradeLevel?->code;

            if (! $gradeCode) {
                throw ValidationException::withMessages([
                    'student' => 'Student grade level not found.',
                ]);
            }

            // Determine whether the learner is Grade 7-10.
            $isJuniorHigh = in_array(
                $gradeCode,
                ['G7', 'G8', 'G9', 'G10'],
                true
            );

            // Connect the existing parent/guardian account.
            $link = $this->link->execute(
                $guardian,
                $student,
                [
                    'relationship_type' => $relationship,
                    'is_primary_contact' => false,
                    'may_receive_sms' => false,

                    // Nursery-Grade 6: Parent-managed.
                    // Grade 7-10: Student-managed.
                    'may_manage_enrollment' => ! $isJuniorHigh,

                    // Allow parent to view financial information.
                    'may_view_financials' => true,
                ],
                $actor
            );

            // Verify the parent-student relationship.
            $this->verify->execute($link, $actor);

            return $link->fresh();
        });
    }
}
