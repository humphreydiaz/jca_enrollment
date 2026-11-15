<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\CurriculumGradeSubject;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Requirements\Actions\EnsureOfficialRequirementRulesAction;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAcademicCatalogAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly EnsureOfficialRequirementRulesAction $requirements,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(Model $record, array $attributes, User $actor, string $action): Model
    {
        return DB::transaction(function () use ($record, $attributes, $actor, $action): Model {
            if ($record instanceof Section && $record->exists) {
                // Renaming is allowed and audited. Moving a section to another school year or grade
                // would detach its students, teachers, and grades from their history, so it is refused
                // once the section came from a curriculum or is in use.
                $inUse = $record->curriculum_id !== null
                    || $record->sectionAssignments()->exists()
                    || $record->teachingAssignments()->exists();
                foreach (['academic_year_id', 'grade_level_id'] as $field) {
                    if ($inUse && array_key_exists($field, $attributes) && (string) $attributes[$field] !== (string) $record->getAttribute($field)) {
                        throw ValidationException::withMessages([$field => 'This section already has a curriculum, students, or teachers, so its school year and grade cannot change. Create a new section and archive this one instead.']);
                    }
                }
            }
            if ($record instanceof Subject && $record->exists && (
                ($attributes['code'] ?? $record->code) !== $record->code ||
                ($attributes['name'] ?? $record->name) !== $record->name
            )) {
                if (CurriculumGradeSubject::query()->where('subject_id', $record->id)
                    ->whereHas('curriculum', fn ($query) => $query->where('status', 'published'))->exists()) {
                    throw ValidationException::withMessages(['name' => 'A subject used by a published curriculum cannot be renamed. Create a new subject for a future version.']);
                }
            }
            $previousName = $record instanceof Section && $record->exists ? $record->name : null;
            $record->fill($attributes);
            $changed = array_keys($record->getDirty());
            $record->save();
            if ($record instanceof AcademicYear) {
                $this->requirements->execute($record, $actor);
            }
            $metadata = $changed === [] || ! str_ends_with($action, '.updated') ? [] : ['changed' => implode(',', $changed)];
            if ($previousName !== null && $previousName !== $record->name) {
                $metadata['previous_name'] = $previousName;
            }
            $this->audit->record($actor, $action, $record, $metadata);

            return $record;
        });
    }
}
