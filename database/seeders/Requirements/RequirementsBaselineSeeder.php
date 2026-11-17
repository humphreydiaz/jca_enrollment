<?php

namespace Database\Seeders\Requirements;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Requirements\Actions\EnsureOfficialRequirementRulesAction;
use App\Modules\Requirements\Actions\GenerateEnrollmentChecklistAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Installs the owner-confirmed requirement rules for every existing school year.
 *
 * Existing rule values are never overwritten. Legacy Form 138 document labels
 * are normalized to SF9, as in the prior seeder. Reruns repair missing years
 * without replacing JCA's year-specific rule decisions.
 */
class RequirementsBaselineSeeder extends Seeder
{
    /** @var list<string> */
    private const IN_FLIGHT_STATUSES = [
        'submitted',
        'under_review',
        'incomplete',
        'assessment_required',
        'assessment_pending',
        'waitlisted',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $years = AcademicYear::query()->orderBy('starts_on')->get();

            if ($years->isEmpty()) {
                throw new RuntimeException('At least one school year is required before installing requirement rules.');
            }

            $ensure = app(EnsureOfficialRequirementRulesAction::class);
            foreach ($years as $year) {
                $ensure->execute($year);
            }

            $generator = app(GenerateEnrollmentChecklistAction::class);
            Enrollment::query()
                ->whereIn('status', self::IN_FLIGHT_STATUSES)
                ->eachById(fn (Enrollment $enrollment) => $generator->execute($enrollment));
        });

        $this->command?->info('Baseline requirement rules installed and in-flight application checklists repaired.');
    }
}
