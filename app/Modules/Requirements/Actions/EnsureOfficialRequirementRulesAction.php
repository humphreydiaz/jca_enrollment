<?php

namespace App\Modules\Requirements\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\Requirements\Models\RequirementRule;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnsureOfficialRequirementRulesAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(AcademicYear $year, ?User $actor = null): int
    {
        return DB::transaction(function () use ($year, $actor): int {
            $catalog = config('jca_requirements');
            $names = $catalog['document_types'] ?? [];
            $categories = $catalog['categories'] ?? [];
            if ($names === [] || $categories === []) {
                throw new RuntimeException('The confirmed JCA requirement catalog is missing.');
            }

            $types = [];
            foreach ($names as $code => $name) {
                $type = DocumentType::query()->firstOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'sensitivity_class' => 'standard', 'is_active' => true],
                );

                if ($code === 'FORM_138' && in_array($type->getRawOriginal('name'), ['Form 138', 'Form 138 / Report Card', 'Report Card/Form 138'], true)) {
                    $type->update(['name' => $name]);
                }
                $types[$code] = $type;
            }

            $added = 0;
            foreach ($categories as $category => $codes) {
                foreach ($codes as $code) {
                    if (! isset($types[$code])) {
                        throw new RuntimeException("Unknown JCA requirement document code: {$code}");
                    }
                    $rule = RequirementRule::query()->firstOrCreate([
                        'academic_year_id' => $year->id,
                        'student_category' => $category,
                        'document_type_id' => $types[$code]->id,
                    ], [
                        'is_required' => true,
                        'is_blocking' => true,
                        'notes' => $catalog['notes'] ?? null,
                    ]);
                    $added += (int) $rule->wasRecentlyCreated;
                }
            }

            if ($added > 0) {
                $this->audit->record($actor, 'requirements.rules.baseline_installed', $year, ['rules_added' => $added]);
            }

            return $added;
        });
    }
}
