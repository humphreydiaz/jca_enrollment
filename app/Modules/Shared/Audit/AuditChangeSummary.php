<?php

namespace App\Modules\Shared\Audit;

use App\Modules\Shared\Audit\Models\AuditLog;
use Illuminate\Support\Str;

/**
 * D-059: what an audit entry says changed, for display. Only these metadata keys are ever shown:
 * "fields" (names of changed fields), "changes" (masked old/new values), and "from"/"to" (status moves).
 * Any other metadata (reasons, provider details, internal notes) stays out of every screen.
 */
final class AuditChangeSummary
{
    private const FIELD_LABELS = [
        'mobile' => 'Mobile number',
        'email' => 'Email',
        'lrn' => 'LRN',
        'ip_group' => 'Indigenous Peoples group',
        'is_4ps_recipient' => '4Ps beneficiary',
        'four_ps_household_id' => '4Ps household ID',
        'is_learner_with_disability' => 'Learner with disability',
        'extension_name' => 'Suffix',
    ];

    private const ACTION_LABELS = [
        'enrollment.status.changed' => 'Enrollment status changed',
        'enrollment.walk_in_access.issued' => 'Walk-in access issued',
        'enrollment.walk_in_access.reissued' => 'Walk-in access issued again',
        'enrollment.draft.saved' => 'Application saved',
        'enrollment.previous_school_corrected' => 'Previous school corrected',
        'enrollment.duplicate.reviewed' => 'Possible duplicate reviewed',
        'people.student.application_details_saved' => 'Learner details saved on the application',
        'people.student.profile_corrected' => 'Learner information corrected',
        'people.student.identifiers_corrected' => 'Student ID or LRN corrected',
        'people.student.number_issued' => 'Student ID issued',
        'people.student.created' => 'Learner record created',
        'people.student.transferred_out' => 'Learner transferred out (SF10 released)',
        'people.student.dropped' => 'Learner dropped (recorded in DepEd LIS)',
        'people.guardian.details_completed' => 'Parent/guardian details saved',
        'people.guardian.created' => 'Parent/guardian record created',
        'people.guardian_student.linked' => 'Parent/guardian linked',
        'people.guardian_student.verified' => 'Parent/guardian link verified',
        'requirements.hard_copy.received' => 'Hard copy received',
        'requirements.requirement.verified' => 'Requirement verified',
        'requirements.requirement.waived' => 'Requirement waived',
        'requirements.requirement.rejected' => 'Requirement rejected',
        'section_assignment.confirmed' => 'Section placement confirmed',
        'section_assignment.transferred' => 'Section transfer',
    ];

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? Str::of($action)->after('.')->replace(['.', '_'], ' ')->ucfirst()->toString();
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? Str::of($field)->replace('_', ' ')->ucfirst()->toString();
    }

    /**
     * recorded=false: the field changed, but its values are personal data kept out of the log.
     *
     * @return list<array{field: string, old: ?string, new: ?string, recorded: bool}>
     */
    public static function lines(AuditLog $log): array
    {
        $metadata = is_array($log->metadata) ? $log->metadata : [];
        $lines = [];

        if (isset($metadata['from'], $metadata['to']) && is_scalar($metadata['from']) && is_scalar($metadata['to'])) {
            $lines[] = ['field' => str_contains($log->action, 'status') ? 'Status' : 'Value',
                'old' => Str::headline((string) $metadata['from']), 'new' => Str::headline((string) $metadata['to']), 'recorded' => true];
        }

        $changes = is_array($metadata['changes'] ?? null) ? $metadata['changes'] : [];
        foreach ($changes as $field => $change) {
            if (is_array($change)) {
                $lines[] = ['field' => self::fieldLabel((string) $field), 'old' => self::scalar($change['old'] ?? null),
                    'new' => self::scalar($change['new'] ?? null), 'recorded' => true];
            }
        }

        $fields = is_array($metadata['fields'] ?? null) ? $metadata['fields'] : [];
        foreach ($fields as $field) {
            if (is_string($field) && ! array_key_exists($field, $changes)) {
                $lines[] = ['field' => self::fieldLabel($field), 'old' => null, 'new' => null, 'recorded' => false];
            }
        }

        return $lines;
    }

    private static function scalar(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
