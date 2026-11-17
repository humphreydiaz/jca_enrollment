<?php

namespace Tests\Unit;

use App\Modules\Academics\Support\ClassSchedule;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassScheduleTest extends TestCase
{
    /** @return array<string, array{string, string, string, string}> */
    public static function recognizedText(): array
    {
        return [
            'official format' => ['Monday-Friday 08:20-09:00', '1,2,3,4,5', '08:20', '09:00'],
            'short range, single-digit hours' => ['Mon-Fri 8:20-9:00', '1,2,3,4,5', '08:20', '09:00'],
            'four days' => ['Mon-Thu 13:00-14:00', '1,2,3,4', '13:00', '14:00'],
            'listed days' => ['Mon, Wed, Fri 07:30-08:15', '1,3,5', '07:30', '08:15'],
            'single day' => ['Saturday 09:00-11:00', '6', '09:00', '11:00'],
        ];
    }

    #[DataProvider('recognizedText')]
    public function test_it_recognizes_class_program_text(string $text, string $days, string $start, string $end): void
    {
        $schedule = ClassSchedule::parse($text);

        $this->assertNotNull($schedule);
        $this->assertSame($days, implode(',', $schedule->days));
        $this->assertSame($start, $schedule->startsAt);
        $this->assertSame($end, $schedule->endsAt);
    }

    public function test_unrecognized_notes_are_left_as_free_text(): void
    {
        $this->assertNull(ClassSchedule::parse('Confirm official meeting time with JCA.'));
        $this->assertNull(ClassSchedule::parse('Mon 8:00 AM'));
        $this->assertSame(
            ['days' => null, 'starts_at' => null, 'ends_at' => null, 'schedule_text' => 'Tue 8:00 AM'],
            ClassSchedule::attributesFromInput(['schedule_text' => 'Tue 8:00 AM']),
        );
    }

    public function test_labels_and_overlaps_follow_the_days(): void
    {
        $weekdays = ClassSchedule::make([5, 1, 2, 3, 4], '08:20', '09:00');
        $this->assertSame('Monday-Friday 08:20-09:00', $weekdays->label());
        $this->assertSame('Mon-Fri', $weekdays->daysLabel());
        $this->assertSame('Mon, Wed', ClassSchedule::make([1, 3], '08:00', '09:00')->daysLabel());

        $this->assertTrue($weekdays->overlaps(ClassSchedule::make([3], '08:59', '09:30')));
        $this->assertFalse($weekdays->overlaps(ClassSchedule::make([1, 2], '09:00', '09:40')));
        $this->assertFalse(ClassSchedule::make([1, 3], '08:00', '09:00')->overlaps(ClassSchedule::make([2, 4], '08:00', '09:00')));
    }

    public function test_invalid_times_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        ClassSchedule::make([1], '10:00', '09:00');
    }

    public function test_empty_day_selection_defaults_to_the_school_week(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], ClassSchedule::make([], '07:00', '07:40')->days);
        $this->assertNull(ClassSchedule::make([1], null, '07:40'));
    }
}
