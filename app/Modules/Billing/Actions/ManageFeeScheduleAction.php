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
     * Create a draft with the next available version number.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes, User $actor): FeeSchedule
    {
        return $this->createFrom($attributes, $actor, null)['schedule'];
    }

    /**
     * Create a draft, optionally copying another version's fees.
     *
     * @param array<string, mixed> $attributes
     * @return array{schedule: FeeSchedule, copied: int, skipped: int}
     */
    public function createFrom(
        array $attributes,
        User $actor,
        ?FeeSchedule $copyFrom
    ): array {
        return DB::transaction(function () use (
            $attributes,
            $actor,
            $copyFrom
        ): array {
            AcademicYear::query()
                ->whereKey($attributes['academic_year_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $schedule = FeeSchedule::query()->create([
                ...$attributes,
                'version' => $this->nextVersion(
                    $attributes['academic_year_id'],
                    $attributes['grade_level_id'] ?? null
                ),
                'status' => 'draft',
            ]);

            $copied = 0;
            $skipped = 0;

            if ($copyFrom !== null) {
                $source = FeeScheduleItem::query()
                    ->where('fee_schedule_id', $copyFrom->id)
                    ->with('feeType')
                    ->orderBy('id')
                    ->get();

                foreach ($source as $item) {
                    if (
                        $item->feeType === null ||
                        ! $item->feeType->is_active
                    ) {
                        $skipped++;
                        continue;
                    }

                    FeeScheduleItem::query()->create([
                        'fee_schedule_id' => $schedule->id,
                        'fee_type_id' => $item->fee_type_id,
                        'description' => $item->description,
                        'amount' => $item->amount,

                        // Preserve Tentative or Final Price.
                        'price_status' => $item->price_status ?? 'final',

                        'is_required' => $item->is_required,
                        'is_default_selected' => $item->is_default_selected,
                    ]);

                    $copied++;
                }
            }

            $this->audit->record(
                $actor,
                'billing.fee_schedule.created',
                $schedule,
                $copyFrom === null ? [] : [
                    'copied_from' => $copyFrom->public_id,
                    'copied_items' => $copied,
                    'skipped_inactive_items' => $skipped,
                ]
            );

            return [
                'schedule' => $schedule,
                'copied' => $copied,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Update the settings of a draft schedule.
     *
     * @param array<string, mixed> $attributes
     */
    public function updateDraft(
        FeeSchedule $schedule,
        array $attributes,
        User $actor
    ): FeeSchedule {
        return DB::transaction(function () use (
            $schedule,
            $attributes,
            $actor
        ): FeeSchedule {
            $locked = FeeSchedule::query()
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Only a draft fee schedule can be edited. A published version is kept as-is; archive it and build a new version instead.',
                ]);
            }

            $yearId = (int) (
                $attributes['academic_year_id']
                ?? $locked->academic_year_id
            );

            $gradeId = array_key_exists(
                'grade_level_id',
                $attributes
            )
                ? $attributes['grade_level_id']
                : $locked->grade_level_id;

            $movedSeries =
                $yearId !== (int) $locked->academic_year_id
                || (int) $gradeId !== (int) $locked->grade_level_id;

            $locked->fill($attributes);

            if ($movedSeries) {
                $locked->version = $this->nextVersion(
                    $yearId,
                    $gradeId
                );
            }

            $locked->save();

            $this->audit->record(
                $actor,
                'billing.fee_schedule.updated',
                $locked
            );

            return $locked;
        });
    }

    /**
     * Delete a schedule only if no assessments depend on it.
     */
    public function delete(
        FeeSchedule $schedule,
        User $actor
    ): void {
        DB::transaction(function () use (
            $schedule,
            $actor
        ): void {
            $locked = FeeSchedule::query()
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            $usage = $this->usageCount($locked);

            if ($usage > 0) {
                throw ValidationException::withMessages([
                    'schedule' => "This version is already used by {$usage} student assessment(s) and cannot be deleted. Archive it instead so it stops being offered while the billing history stays intact.",
                ]);
            }

            $this->audit->record(
                $actor,
                'billing.fee_schedule.deleted',
                $locked,
                [
                    'name' => $locked->name,
                    'version' => $locked->version,
                    'status' => $locked->status,
                ]
            );

            // Keep the existing mass-delete behavior.
            FeeScheduleItem::query()
                ->where('fee_schedule_id', $locked->id)
                ->delete();

            $locked->delete();
        });
    }

    /**
     * Archive or restore a published fee schedule.
     */
    public function setArchived(
        FeeSchedule $schedule,
        bool $archived,
        User $actor
    ): FeeSchedule {
        return DB::transaction(function () use (
            $schedule,
            $archived,
            $actor
        ): FeeSchedule {
            $locked = FeeSchedule::query()
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            $expected = $archived
                ? 'published'
                : 'archived';

            if ($locked->status !== $expected) {
                throw ValidationException::withMessages([
                    'schedule' => $archived
                        ? 'Only a published fee schedule can be archived.'
                        : 'Only an archived fee schedule can be restored.',
                ]);
            }

            $locked->status = $archived
                ? 'archived'
                : 'published';

            $locked->save();

            $this->audit->record(
                $actor,
                $archived
                    ? 'billing.fee_schedule.archived'
                    : 'billing.fee_schedule.restored',
                $locked
            );

            return $locked;
        });
    }

    /**
     * Count assessments from this schedule version.
     */
    public function usageCount(FeeSchedule $schedule): int
    {
        return FeeAssessment::query()
            ->where('fee_schedule_id', $schedule->id)
            ->count();
    }

    /**
     * Get the next version number for year and grade.
     */
    public function nextVersion(
        int $academicYearId,
        int|string|null $gradeLevelId
    ): int {
        $highest = FeeSchedule::query()
            ->where('academic_year_id', $academicYearId)
            ->when(
                $gradeLevelId,
                fn ($query, $gradeId) =>
                    $query->where('grade_level_id', $gradeId),
                fn ($query) =>
                    $query->whereNull('grade_level_id'),
            )
            ->max('version');

        return (int) $highest + 1;
    }
}
