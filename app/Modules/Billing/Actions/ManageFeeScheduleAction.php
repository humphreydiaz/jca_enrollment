<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageFeeScheduleAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * Create a draft. The version is assigned by the system — it is the next
     * number for this school year and grade — so an admin can never collide
     * with an existing version or have to guess which number is free.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): FeeSchedule
    {
        return DB::transaction(function () use ($attributes, $actor): FeeSchedule {
            AcademicYear::query()->whereKey($attributes['academic_year_id'])->lockForUpdate()->firstOrFail();

            $schedule = FeeSchedule::query()->create([
                ...$attributes,
                'version' => $this->nextVersion($attributes['academic_year_id'], $attributes['grade_level_id'] ?? null),
                'status' => 'draft',
            ]);
            $this->audit->record($actor, 'billing.fee_schedule.created', $schedule);

            return $schedule;
        });
    }

    /**
     * Correct a draft's settings — including the grade level, which is the
     * mistake most worth fixing without publishing first.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateDraft(FeeSchedule $schedule, array $attributes, User $actor): FeeSchedule
    {
        return DB::transaction(function () use ($schedule, $attributes, $actor): FeeSchedule {
            $locked = FeeSchedule::query()->lockForUpdate()->findOrFail($schedule->id);

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Only a draft fee schedule can be edited. A published version is kept as-is; archive it and build a new version instead.',
                ]);
            }

            $yearId = (int) ($attributes['academic_year_id'] ?? $locked->academic_year_id);
            $gradeId = array_key_exists('grade_level_id', $attributes)
                ? $attributes['grade_level_id']
                : $locked->grade_level_id;

            // Moving the draft to a different year or grade puts it in another
            // version series, so it takes the next free number over there.
            $movedSeries = $yearId !== (int) $locked->academic_year_id
                || (int) $gradeId !== (int) $locked->grade_level_id;

            $locked->fill($attributes);
            if ($movedSeries) {
                $locked->version = $this->nextVersion($yearId, $gradeId);
            }
            $locked->save();

            $this->audit->record($actor, 'billing.fee_schedule.updated', $locked);

            return $locked;
        });
    }

    /**
     * Remove a schedule outright. Permitted only while nothing financial hangs
     * off it: a draft, or a published/archived version that never produced an
     * assessment. Anything already billed is kept for the audit trail.
     */
    public function delete(FeeSchedule $schedule, User $actor): void
    {
        DB::transaction(function () use ($schedule, $actor): void {
            $locked = FeeSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $usage = $this->usageCount($locked);

            if ($usage > 0) {
                throw ValidationException::withMessages([
                    'schedule' => "This version is already used by {$usage} student assessment(s) and cannot be deleted. Archive it instead so it stops being offered while the billing history stays intact.",
                ]);
            }

            $this->audit->record($actor, 'billing.fee_schedule.deleted', $locked, [
                'name' => $locked->name,
                'version' => $locked->version,
                'status' => $locked->status,
            ]);

            // Mass delete on purpose: the per-item immutability guard protects a
            // live published schedule from being edited, but this removes an
            // unused version whole, items included.
            FeeScheduleItem::query()->where('fee_schedule_id', $locked->id)->delete();
            $locked->delete();
        });
    }

    /**
     * Retire a published version, or bring a retired one back. An archived
     * schedule is no longer offered for new assessments while every assessment
     * already made from it stays readable.
     */
    public function setArchived(FeeSchedule $schedule, bool $archived, User $actor): FeeSchedule
    {
        return DB::transaction(function () use ($schedule, $archived, $actor): FeeSchedule {
            $locked = FeeSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $expected = $archived ? 'published' : 'archived';

            if ($locked->status !== $expected) {
                throw ValidationException::withMessages([
                    'schedule' => $archived
                        ? 'Only a published fee schedule can be archived.'
                        : 'Only an archived fee schedule can be restored.',
                ]);
            }

            $locked->status = $archived ? 'archived' : 'published';
            $locked->save();

            $this->audit->record(
                $actor,
                $archived ? 'billing.fee_schedule.archived' : 'billing.fee_schedule.restored',
                $locked,
            );

            return $locked;
        });
    }

    /** Student assessments issued from this exact version. */
    public function usageCount(FeeSchedule $schedule): int
    {
        return FeeAssessment::query()->where('fee_schedule_id', $schedule->id)->count();
    }

    public function nextVersion(int $academicYearId, int|string|null $gradeLevelId): int
    {
        $highest = FeeSchedule::query()
            ->where('academic_year_id', $academicYearId)
            ->when(
                $gradeLevelId,
                fn ($query, $gradeId) => $query->where('grade_level_id', $gradeId),
                fn ($query) => $query->whereNull('grade_level_id'),
            )
            ->max('version');

        return (int) $highest + 1;
    }
}
