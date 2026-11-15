<?php

namespace Tests\Unit;

use App\Modules\Academics\Support\ActivityProgram;
use PHPUnit\Framework\TestCase;

class ActivityProgramTest extends TestCase
{
    public function test_a_preschool_program_becomes_class_hours_and_timed_activities_in_order(): void
    {
        $program = ActivityProgram::parse('First batch, 08:00-11:00. Free play 08:00; meeting 08:15; activities 08:35, 09:05, 09:50; recess 09:35; wrap-up 10:55.');

        $this->assertSame(['label' => 'First batch', 'start' => '08:00', 'end' => '11:00'], $program['hours']);
        $this->assertSame([
            ['activity' => 'Free play', 'start' => '08:00', 'end' => '08:15'],
            ['activity' => 'Meeting', 'start' => '08:15', 'end' => '08:35'],
            ['activity' => 'Activities', 'start' => '08:35', 'end' => '09:05'],
            ['activity' => 'Activities', 'start' => '09:05', 'end' => '09:35'],
            ['activity' => 'Recess', 'start' => '09:35', 'end' => '09:50'],
            ['activity' => 'Activities', 'start' => '09:50', 'end' => '10:55'],
            ['activity' => 'Wrap-up', 'start' => '10:55', 'end' => '11:00'],
        ], $program['rows']);
        $this->assertSame([], $program['notes']);
    }

    public function test_unlabelled_hours_numbered_activities_and_untimed_notes(): void
    {
        $program = ActivityProgram::parse('11:00-13:00. Free play 11:00; activity 1 11:35; wrap-up 12:55. Bring a snack.');

        $this->assertSame(['label' => null, 'start' => '11:00', 'end' => '13:00'], $program['hours']);
        $this->assertSame(['Free play', 'Activity 1', 'Wrap-up'], array_column($program['rows'], 'activity'));
        $this->assertSame('13:00', $program['rows'][2]['end']);
        $this->assertSame(['Bring a snack'], $program['notes']);
        $this->assertSame(['hours' => null, 'rows' => [], 'notes' => []], ActivityProgram::parse('  '));
    }
}
