<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Support\ClassSchedule;

/** Adds a subject to one section (outside the curriculum) with an optional class time. */
class SaveSectionSubjectAction
{
    public function __construct(private readonly SaveAcademicCatalogAction $save) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input, User $actor): SectionSubject
    {
        /** @var SectionSubject $offering */
        $offering = $this->save->execute(new SectionSubject, [
            'section_id' => $input['section_id'],
            'subject_id' => $input['subject_id'],
            'status' => 'active',
            ...ClassSchedule::attributesFromInput($input),
        ], $actor, 'academics.section_subject.created');

        return $offering;
    }
}
