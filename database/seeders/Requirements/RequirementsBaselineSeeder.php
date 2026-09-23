<?php

namespace Database\Seeders\Requirements;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Requirements\Actions\GenerateEnrollmentChecklistAction;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\Requirements\Models\RequirementRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Installs the minimum requirement rules confirmed by REQ-002 through REQ-004.
 *
 * Existing document types and rules are never overwritten. This makes the
 * seeder safe to rerun after JCA has customized a rule, while still repairing
 * hosted databases that were created without any requirement configuration.
 */
class RequirementsBaselineSeeder extends Seeder
{
    /** @var array<string, string> */
    private const DOCUMENT_TYPES = [
        'BIRTH_CERTIFICATE' => 'Birth Certificate',
        'FORM_138' => 'Form 138 / Report Card',
        'FORM_137' => 'Form 137',
        'CERTIFICATE_LOV' => 'Certificate of LOV',
    ];

    /** @var array<string, list<string>> */
    private const RULES = [
        'regular' => ['BIRTH_CERTIFICATE', 'FORM_138'],
        'transferee' => ['BIRTH_CERTIFICATE', 'FORM_138', 'FORM_137'],
        'support_review' => ['BIRTH_CERTIFICATE', 'CERTIFICATE_LOV'],
    ];

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
            $inFlightYearIds = Enrollment::query()
                ->whereIn('status', self::IN_FLIGHT_STATUSES)
                ->select('academic_year_id');

            $years = AcademicYear::query()
                ->where(fn ($query) => $query
                    ->whereIn('status', ['planned', 'open'])
                    ->orWhereIn('id', $inFlightYearIds))
                ->orderBy('starts_on')
                ->get();

            if ($years->isEmpty()) {
                throw new RuntimeException('At least one planned/open school year or in-flight application is required before installing requirement rules.');
            }

            $types = collect(self::DOCUMENT_TYPES)->mapWithKeys(function (string $name, string $code): array {
                $type = DocumentType::query()->firstOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'sensitivity_class' => 'standard', 'is_active' => true],
                );

                return [$code => $type];
            });

            foreach ($years as $year) {
                foreach (self::RULES as $category => $codes) {
                    foreach ($codes as $code) {
                        RequirementRule::query()->firstOrCreate([
                            'academic_year_id' => $year->id,
                            'student_category' => $category,
                            'document_type_id' => $types[$code]->id,
                        ], [
                            'is_required' => true,
                            'is_blocking' => true,
                            'notes' => 'Upload a readable soft copy for review or submit the hard copy to the JCA Registrar. The hard copy remains required after soft-copy verification.',
                        ]);
                    }
                }
            }

            $generator = app(GenerateEnrollmentChecklistAction::class);
            Enrollment::query()
                ->whereIn('academic_year_id', $years->modelKeys())
                ->whereIn('status', self::IN_FLIGHT_STATUSES)
                ->eachById(fn (Enrollment $enrollment) => $generator->execute($enrollment));
        });

        $this->command?->info('Baseline requirement rules installed and in-flight application checklists repaired.');
    }
}
