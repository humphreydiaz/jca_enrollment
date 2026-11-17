<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\Section;
use Illuminate\Support\Facades\DB;

/** Creates an extra section and gives it its grade's published-curriculum subjects. */
class CreateSectionAction
{
    public function __construct(
        private readonly SaveAcademicCatalogAction $save,
        private readonly AddMissingCurriculumSubjectsAction $curriculumSubjects,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes, User $actor): Section
    {
        return DB::transaction(function () use ($attributes, $actor): Section {
            /** @var Section $section */
            $section = $this->save->execute(new Section, $attributes, $actor, 'academics.section.created');
            $this->curriculumSubjects->execute($section, $actor, recordWhenEmpty: false);

            return $section;
        });
    }
}
