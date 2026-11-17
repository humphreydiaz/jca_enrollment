<?php

namespace Tests\Unit;

use App\Modules\Academics\Models\Section;
use PHPUnit\Framework\TestCase;

class SectionActivityLinesTest extends TestCase
{
    public function test_a_preschool_schedule_becomes_one_activity_per_line(): void
    {
        $section = new Section(['activity_schedule' => 'First batch, 08:00-11:00. Free play 08:00; meeting 08:15; activities 08:35, 09:05, 09:50; wrap-up 10:55.']);

        $this->assertSame(['First batch, 08:00-11:00', 'Free play 08:00', 'Meeting 08:15', 'Activities 08:35, 09:05, 09:50', 'Wrap-up 10:55'], $section->activityLines());
    }

    public function test_sentences_split_too_and_empty_schedules_have_no_lines(): void
    {
        $section = new Section(['activity_schedule' => 'Monday-Thursday: Reading and Math enhancement, 12:40-13:40. Friday: HGP.']);

        $this->assertSame(['Monday-Thursday: Reading and Math enhancement, 12:40-13:40', 'Friday: HGP'], $section->activityLines());
        $this->assertSame([], (new Section)->activityLines());
        $this->assertSame([], (new Section(['activity_schedule' => '  ']))->activityLines());
    }
}
