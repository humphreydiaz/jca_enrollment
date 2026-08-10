<?php

namespace Database\Seeders\Requirements;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Requirements\Actions\GenerateEnrollmentChecklistAction;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\Requirements\Models\RequirementRule;
use Illuminate\Database\Seeder;
use RuntimeException;

class RequirementsDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('The requirements development seeder may run only in the local environment.');
        }

        $year = AcademicYear::query()->where('code', '2026-2027')->first();

        if ($year === null) {
            throw new RuntimeException('School Year 2026-2027 must exist before seeding requirement rules.');
        }

        $types = collect([
            'BIRTH_CERTIFICATE' => 'Birth Certificate',
            'FORM_138' => 'Form 138 / Report Card',
            'FORM_137' => 'Form 137',
            'CERTIFICATE_LOV' => 'Certificate of LOV',
        ])->mapWithKeys(function (string $name, string $code): array {
            $type = DocumentType::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sensitivity_class' => 'standard', 'is_active' => true],
            );

            return [$code => $type];
        });

        $rules = [
            'regular' => ['BIRTH_CERTIFICATE', 'FORM_138'],
            'transferee' => ['BIRTH_CERTIFICATE', 'FORM_138', 'FORM_137'],
            'support_review' => ['BIRTH_CERTIFICATE', 'CERTIFICATE_LOV'],
        ];

        foreach ($rules as $category => $codes) {
            foreach ($codes as $code) {
                RequirementRule::query()->updateOrCreate([
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

        $generator = app(GenerateEnrollmentChecklistAction::class);
        Enrollment::query()
            ->where('academic_year_id', $year->id)
            ->whereIn('status', ['submitted', 'under_review', 'incomplete', 'assessment_required', 'assessment_pending'])
            ->eachById(fn (Enrollment $enrollment) => $generator->execute($enrollment));
    }
}
