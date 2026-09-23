<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Events\EnrollmentApproved;
use App\Modules\Enrollment\Events\EnrollmentStatusChanged;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentStatusHistory;
use App\Modules\People\Actions\IssueStudentNumberAction;
use App\Modules\Requirements\Actions\AssertEnrollmentRequirementsSatisfiedAction;
use App\Modules\Requirements\Actions\GenerateEnrollmentChecklistAction;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Models\SupportCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionEnrollmentAction
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['under_review', 'incomplete', 'assessment_required', 'waitlisted', 'rejected', 'cancelled'],
        'under_review' => ['incomplete', 'assessment_required', 'approved', 'waitlisted', 'rejected', 'cancelled'],
        'incomplete' => ['submitted', 'rejected', 'cancelled'],
        'assessment_required' => ['assessment_pending', 'rejected', 'cancelled'],
        'assessment_pending' => ['under_review', 'rejected', 'cancelled'],
        'waitlisted' => ['under_review', 'rejected', 'cancelled'],
        'approved' => ['withdrawn'],
    ];

    public function __construct(private AuditRecorder $audit, private GenerateEnrollmentChecklistAction $checklist, private AssertEnrollmentRequirementsSatisfiedAction $requirementsSatisfied, private IssueStudentNumberAction $issueStudentNumber) {}

    /** @return list<string> */
    public static function allowedTransitionsFrom(string $status): array
    {
        return self::ALLOWED_TRANSITIONS[$status] ?? [];
    }

    public function execute(Enrollment $enrollment, string $to, ?User $actor, string $reason = ''): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $to, $actor, $reason) {
            $record = Enrollment::query()->lockForUpdate()->with('requirements')->findOrFail($enrollment->id);
            $from = $record->status;
            if (! in_array($to, self::allowedTransitionsFrom($from), true)) {
                throw ValidationException::withMessages(['status' => 'This enrollment transition is not allowed.']);
            }
            if ($to === 'submitted' && $record->contact_verified_at === null) {
                throw ValidationException::withMessages(['status' => 'Contact verification is required before submission.']);
            }
            if ($to === 'under_review' && $record->duplicate_review_status === 'pending') {
                throw ValidationException::withMessages(['status' => 'Potential duplicate identity must be reviewed first.']);
            }
            if ($to === 'submitted') {
                if ($this->checklist->execute($record) === 0) {
                    throw ValidationException::withMessages([
                        'application' => 'Enrollment requirements are not configured for this school year and applicant category. Please contact the JCA Registrar before submitting.',
                    ]);
                }
                $record->submitted_at = now();
                // The learner's permanent student number is issued the moment the
                // application is submitted, so they can sign in with it while the
                // application is still under review — and it never changes later.
                $record->loadMissing('student', 'academicYear');
                if ($record->student !== null && $record->academicYear !== null) {
                    $this->issueStudentNumber->execute($record->student, $record->academicYear);
                }
            }
            if ($to === 'approved') {
                $record->load('requirements');
                if ($record->duplicate_review_status === 'pending') {
                    throw ValidationException::withMessages(['status' => 'Potential duplicate identity must be reviewed first.']);
                }
                $this->requirementsSatisfied->execute($record);
                if ($record->student_category === 'support_review' && ! SupportCase::query()->where('enrollment_id', $record->id)->where('final_outcome', 'approved')->exists()) {
                    throw ValidationException::withMessages(['status' => 'An approved support review is required.']);
                }
                $record->approved_at = now();
            }
            $record->status = $to;
            if (in_array($to, ['rejected', 'withdrawn', 'cancelled'], true)) {
                if ($to === 'rejected') {
                    $record->rejected_at = now();
                }
                if ($to === 'withdrawn') {
                    $record->withdrawn_at = now();
                }
                $record->active_enrollment_key = null;
                $record->active_student_application_key = null;
            }
            $record->decided_by = $actor?->id;
            $record->decision_reason = $reason ?: null;
            $record->lock_version++;
            $record->save();
            EnrollmentStatusHistory::query()->create(['enrollment_id' => $record->id, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason ?: null, 'changed_by' => $actor?->id, 'changed_at' => now()]);
            if (in_array($to, ['approved', 'rejected', 'waitlisted'], true)) {
                DB::table('enrollment_decisions')->insert([
                    'enrollment_id' => $record->id,
                    'decision_type' => 'academic_admission',
                    'outcome' => $to,
                    'reason' => $reason ?: null,
                    'decided_by' => $actor?->id,
                    'decided_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->audit->record($actor, 'enrollment.status.changed', $record, ['from' => $from, 'to' => $to]);
            event(new EnrollmentStatusChanged($record, $from, $to, $actor));
            if ($to === 'approved') {
                event(new EnrollmentApproved($record, $actor));
            }

            return $record;
        });
    }
}
