<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Support\AcademicRecordUsage;
use App\Modules\Requirements\Actions\RemoveUnusedYearRequirementRulesAction;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permanently deletes an academic catalog record created by mistake. Any record that is already
 * used by enrollments, placements, grades, finance, or curriculum history is refused and must be
 * archived instead.
 */
class DeleteUnusedAcademicRecordAction
{
    public function __construct(
        private readonly AcademicRecordUsage $usage,
        private readonly AuditRecorder $audit,
        private readonly RemoveUnusedYearRequirementRulesAction $yearRequirements,
    ) {}

    public function execute(Model $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor): void {
            $locked = $record::query()->lockForUpdate()->findOrFail($record->getKey());
            $usage = $this->usage->for($locked);
            if ($usage !== []) {
                throw ValidationException::withMessages(['delete' => sprintf(
                    '%s is still used by %s. Archive it instead so that history is kept.',
                    $this->label($locked),
                    $this->usage->describe($usage),
                )]);
            }

            $metadata = ['label' => $this->label($locked)];
            if ($locked instanceof Section) {
                $metadata['offerings_removed'] = SectionSubject::query()->where('section_id', $locked->id)->delete();
            }
            if ($locked instanceof AcademicYear) {
                $this->yearRequirements->execute($locked, $actor);
            }

            $this->audit->record($actor, $this->auditAction($locked), $locked, $metadata);
            $locked->delete();
        });
    }

    private function label(Model $record): string
    {
        return match (true) {
            $record instanceof AcademicYear => 'School year '.$record->code,
            $record instanceof GradeLevel => $record->name,
            $record instanceof Subject => 'Subject '.$record->code,
            $record instanceof Section => 'Section '.$record->name,
            $record instanceof SectionSubject => 'This subject offering',
            $record instanceof TeachingAssignment => 'This teaching assignment',
            default => 'This record',
        };
    }

    private function auditAction(Model $record): string
    {
        return 'academics.'.match (true) {
            $record instanceof AcademicYear => 'academic_year',
            $record instanceof GradeLevel => 'grade_level',
            $record instanceof Subject => 'subject',
            $record instanceof Section => 'section',
            $record instanceof SectionSubject => 'section_subject',
            $record instanceof TeachingAssignment => 'teaching_assignment',
        }.'.deleted';
    }
}
