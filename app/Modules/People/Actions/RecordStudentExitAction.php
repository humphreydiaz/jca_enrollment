<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\StudentExitOptions;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Marks a learner as Transferred Out or Dropped and archives the learner record.
 *
 * Only the learner profile (People module) changes. Enrollment, grade, section,
 * requirement, and finance history is preserved untouched; screens show the
 * learner's exit status instead of "Enrolled".
 */
class RecordStudentExitAction
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $details  validated form input
     */
    public function execute(StudentProfile $student, string $exitStatus, array $details, User $actor, string $errorBag = 'default'): StudentProfile
    {
        if (! in_array($exitStatus, [StudentExitOptions::TRANSFERRED_OUT, StudentExitOptions::DROPPED], true)) {
            throw new InvalidArgumentException('Unsupported learner exit status.');
        }

        return DB::transaction(function () use ($student, $exitStatus, $details, $actor, $errorBag): StudentProfile {
            /** @var StudentProfile $record */
            $record = StudentProfile::query()
                ->whereKey($student->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($record->archived_at !== null || $record->status !== 'active') {
                throw ValidationException::withMessages([
                    'reason' => 'This learner is no longer active or the record has already been archived.',
                ])->errorBag($errorBag);
            }

            $effectiveOn = Carbon::parse((string) $details['effective_on'])->startOfDay();
            $reason = trim((string) $details['reason']);
            $previousStatus = $record->status;

            $record->status = $exitStatus;
            $record->archived_at = now();

            if ($exitStatus === StudentExitOptions::TRANSFERRED_OUT) {
                $record->transferred_out_at = $effectiveOn;
                $record->exit_category = StudentExitOptions::TRANSFERRED_OUT;
                $record->exit_receiving_school = trim((string) $details['receiving_school']);
                $record->archive_reason = $this->transferSummary($details, $effectiveOn, $reason);
                $metadata = [
                    'receiving_school' => trim((string) $details['receiving_school']),
                    'receiving_school_id' => filled($details['receiving_school_id'] ?? null) ? trim((string) $details['receiving_school_id']) : null,
                    'sf10_release_method' => (string) $details['sf10_release_method'],
                    'sf10_released_on' => Carbon::parse((string) $details['sf10_released_on'])->toDateString(),
                    'sf10_released_to' => filled($details['sf10_released_to'] ?? null) ? trim((string) $details['sf10_released_to']) : null,
                ];
                $action = 'people.student.transferred_out';
            } else {
                $category = (string) ($details['drop_category'] ?? 'other_drop');
                $record->exit_category = array_key_exists($category, StudentExitOptions::DROP_CATEGORIES) ? $category : 'other_drop';
                $record->dropped_at = $effectiveOn->toDateString();
                $record->archive_reason = $this->dropSummary($effectiveOn, $reason, $record->exit_category);
                $metadata = ['lis_dropped_confirmed' => true, 'drop_category' => $record->exit_category];
                $action = 'people.student.dropped';
            }

            $record->save();

            $this->audit->record($actor, $action, $record, array_merge([
                'previous_status' => $previousStatus,
                'new_status' => $exitStatus,
                'effective_on' => $effectiveOn->toDateString(),
                'archive_reason' => $reason,
                'student_public_id' => $record->public_id,
            ], $metadata));

            return $record;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function transferSummary(array $details, Carbon $effectiveOn, string $reason): string
    {
        $school = trim((string) $details['receiving_school']);
        $schoolId = trim((string) ($details['receiving_school_id'] ?? ''));
        $address = trim((string) ($details['receiving_school_address'] ?? ''));
        $method = StudentExitOptions::releaseMethodLabel((string) $details['sf10_release_method']);
        $releasedOn = Carbon::parse((string) $details['sf10_released_on'])->format('F j, Y');
        $releasedTo = trim((string) ($details['sf10_released_to'] ?? ''));

        $lines = [
            'Transferred out to: '.$school.($schoolId !== '' ? ' (School ID '.$schoolId.')' : ''),
        ];
        if ($address !== '') {
            $lines[] = 'Receiving school address: '.$address;
        }
        $lines[] = 'Transfer date: '.$effectiveOn->format('F j, Y');
        $lines[] = 'SF10 (formerly Form 137): '.$method.' on '.$releasedOn.($releasedTo !== '' ? ' - carried by '.$releasedTo : '');
        $lines[] = 'Remarks: '.$reason;

        return implode("\n", $lines);
    }

    private function dropSummary(Carbon $effectiveOn, string $reason, string $category): string
    {
        return implode("\n", [
            'Dropped effective: '.$effectiveOn->format('F j, Y'),
            'Kind of drop: '.(StudentExitOptions::DROP_CATEGORIES[$category] ?? $category),
            'DepEd LIS: drop recorded directly in LIS',
            'Reason: '.$reason,
        ]);
    }
}
