<?php

namespace App\Modules\Enrollment\Support;

use App\Modules\Enrollment\Models\Enrollment;

/**
 * Whether a walk-in application form has every field required before submission (D-049).
 * Used by the applicant's own Submit button and by the Registrar's status control, so an
 * unfilled draft cannot be pushed to Submitted from either side.
 */
class EnrollmentApplicationCompleteness
{
    /** @return list<string> Plain-language names of what is still missing (empty when complete). */
    public function missing(Enrollment $enrollment): array
    {
        $enrollment->loadMissing(['student', 'guardian']);
        $student = $enrollment->student;
        $missing = [];

        if ($student === null) {
            return ['learner record'];
        }
        if (collect([$student->birth_place, $student->mother_tongue])->contains(fn ($value): bool => blank($value))) {
            $missing[] = 'learner background';
        }
        if (blank($student->current_address) || blank($student->permanent_address)) {
            $missing[] = 'addresses';
        }
        if ($student->is_4ps_recipient === null || $student->is_learner_with_disability === null) {
            $missing[] = '4Ps and disability answers';
        }
        // D-047: the parent/guardian details must be filled in on the application before submitting.
        if (blank($enrollment->guardian_relationship) || (blank($enrollment->guardian?->mobile) && blank($enrollment->guardian?->email))) {
            $missing[] = 'parent/guardian details';
        }
        if ($enrollment->student_category === 'transferee' && (blank($enrollment->previous_school_name) || blank($enrollment->previous_school_address))) {
            $missing[] = 'previous school';
        }

        return $missing;
    }

    public function isComplete(Enrollment $enrollment): bool
    {
        return $this->missing($enrollment) === [];
    }

    /**
     * Drafts the applicant fills in themselves (walk-in or legacy). A continuing student's promotion
     * draft is prepared and submitted by the Registrar from the previous year's records instead.
     */
    public function appliesTo(Enrollment $enrollment): bool
    {
        return $enrollment->previous_enrollment_id === null;
    }
}
