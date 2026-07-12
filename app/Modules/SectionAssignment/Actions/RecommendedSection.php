<?php

namespace App\Modules\SectionAssignment\Actions;

use App\Modules\Academics\Models\Section;

class RecommendedSection
{
    public function __construct(
        public readonly ?Section $section,
        public readonly string $reason,
        public readonly ?int $confirmedCount = null,
    ) {}
}
