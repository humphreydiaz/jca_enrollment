<?php

namespace App\Modules\Academics\Support;

use Illuminate\Validation\ValidationException;

/**
 * Structured weekly class meeting: a set of ISO weekdays (1 = Monday ... 6 = Saturday)
 * plus a 24-hour start and end time. The human-readable `schedule_text` shown across the
 * portal is always derived from these parts so every screen and the conflict check agree.
 */
final class ClassSchedule
{
    public const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    public const SHORT_WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];

    public const SCHOOL_WEEK = [1, 2, 3, 4, 5];

    /** @param list<int> $days */
    private function __construct(
        public readonly array $days,
        public readonly string $startsAt,
        public readonly string $endsAt,
    ) {}

    /** @param iterable<int|string>|string|null $days */
    public static function make(iterable|string|null $days, ?string $startsAt, ?string $endsAt): ?self
    {
        $startsAt = self::normalizeTime($startsAt);
        $endsAt = self::normalizeTime($endsAt);
        if ($startsAt === null || $endsAt === null) {
            return null;
        }

        $dayList = match (true) {
            is_string($days) => explode(',', $days),
            $days === null => [],
            is_array($days) => $days,
            default => iterator_to_array($days, false),
        };
        $dayList = array_values(array_unique(array_filter(
            array_map('intval', $dayList),
            fn (int $day): bool => isset(self::WEEKDAYS[$day]),
        )));
        sort($dayList);
        if ($dayList === []) {
            $dayList = self::SCHOOL_WEEK;
        }
        if ($startsAt >= $endsAt) {
            throw ValidationException::withMessages(['ends_at' => 'The class must end after it starts.']);
        }

        return new self($dayList, $startsAt, $endsAt);
    }

    /**
     * Recognize the class-program formats staff already use, e.g. "Monday-Friday 08:20-09:00",
     * "Mon-Fri 8:20-9:00", "Mon-Thu 13:00-14:00" or "Mon, Wed, Fri 07:30-08:15".
     */
    public static function parse(?string $text): ?self
    {
        if (! is_string($text) || trim($text) === '') {
            return null;
        }
        $pattern = '/^\s*(?<days>[A-Za-z ,\-–]+?)\s+(?<sh>\d{1,2}):(?<sm>\d{2})\s*[-–]\s*(?<eh>\d{1,2}):(?<em>\d{2})\s*$/u';
        if (! preg_match($pattern, $text, $match)) {
            return null;
        }
        $days = self::parseDays($match['days']);
        if ($days === null) {
            return null;
        }
        foreach (['sh' => 23, 'eh' => 23, 'sm' => 59, 'em' => 59] as $part => $max) {
            if ((int) $match[$part] > $max) {
                throw ValidationException::withMessages(['schedule_text' => 'Use a valid 24-hour start and end time.']);
            }
        }
        $start = sprintf('%02d:%02d', $match['sh'], $match['sm']);
        $end = sprintf('%02d:%02d', $match['eh'], $match['em']);
        if ($start >= $end) {
            throw ValidationException::withMessages(['schedule_text' => 'Use a valid 24-hour start and end time.']);
        }

        return new self($days, $start, $end);
    }

    /**
     * Build the stored attributes for a schedule form. Structured inputs win; a typed note is kept
     * as-is (and parsed when it follows a recognized format) so older free-text entries still work.
     *
     * @param  array<string, mixed>  $input
     * @return array{days: ?string, starts_at: ?string, ends_at: ?string, schedule_text: ?string}
     */
    public static function attributesFromInput(array $input): array
    {
        $hasStructured = filled($input['starts_at'] ?? null) || filled($input['ends_at'] ?? null);
        if ($hasStructured) {
            $schedule = self::make($input['days'] ?? null, $input['starts_at'] ?? null, $input['ends_at'] ?? null);
            if ($schedule === null) {
                throw ValidationException::withMessages(['starts_at' => 'Enter both a start and an end time.']);
            }

            return $schedule->attributes();
        }

        $text = isset($input['schedule_text']) ? trim((string) $input['schedule_text']) : '';
        if ($text === '') {
            return ['days' => null, 'starts_at' => null, 'ends_at' => null, 'schedule_text' => null];
        }
        $parsed = self::parse($text);

        return $parsed === null
            ? ['days' => null, 'starts_at' => null, 'ends_at' => null, 'schedule_text' => $text]
            : [...$parsed->attributes(), 'schedule_text' => $text];
    }

    /** Read the effective schedule of a stored offering or template row. */
    public static function fromRecord(object $record): ?self
    {
        $structured = self::make($record->days ?? null, $record->starts_at ?? null, $record->ends_at ?? null);

        return $structured ?? self::parse($record->schedule_text ?? null);
    }

    /** @return array{days: string, starts_at: string, ends_at: string, schedule_text: string} */
    public function attributes(): array
    {
        return [
            'days' => implode(',', $this->days),
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'schedule_text' => $this->label(),
        ];
    }

    public function label(): string
    {
        return $this->daysLabel(full: true).' '.$this->startsAt.'-'.$this->endsAt;
    }

    public function daysLabel(bool $full = false): string
    {
        $names = $full ? self::WEEKDAYS : self::SHORT_WEEKDAYS;
        $first = $this->days[0];
        $last = $this->days[count($this->days) - 1];
        $contiguous = $this->days === range($first, $last);
        if ($contiguous && count($this->days) >= 3) {
            return $names[$first].'-'.$names[$last];
        }

        return implode(', ', array_map(fn (int $day): string => $names[$day], $this->days));
    }

    public function overlaps(self $other): bool
    {
        return array_intersect($this->days, $other->days) !== []
            && $this->startsAt < $other->endsAt
            && $other->startsAt < $this->endsAt;
    }

    private static function normalizeTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($value), $match) || (int) $match[1] > 23 || (int) $match[2] > 59) {
            throw ValidationException::withMessages(['starts_at' => 'Use a valid 24-hour time.']);
        }

        return sprintf('%02d:%02d', $match[1], $match[2]);
    }

    /** @return list<int>|null */
    private static function parseDays(string $text): ?array
    {
        $lookup = [];
        foreach (self::WEEKDAYS as $number => $name) {
            $lookup[strtolower($name)] = $number;
            $lookup[strtolower(self::SHORT_WEEKDAYS[$number])] = $number;
        }
        $lookup['tues'] = 2;
        $lookup['thur'] = 4;
        $lookup['thurs'] = 4;
        $text = strtolower(trim(str_replace('–', '-', $text)));

        if (preg_match('/^([a-z]+)\s*-\s*([a-z]+)$/', $text, $range)) {
            $from = $lookup[$range[1]] ?? null;
            $to = $lookup[$range[2]] ?? null;

            return $from !== null && $to !== null && $from <= $to ? range($from, $to) : null;
        }

        $days = [];
        foreach (preg_split('/\s*,\s*|\s+/', $text) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            if (! isset($lookup[$token])) {
                return null;
            }
            $days[] = $lookup[$token];
        }
        $days = array_values(array_unique($days));
        sort($days);

        return $days === [] ? null : $days;
    }
}
