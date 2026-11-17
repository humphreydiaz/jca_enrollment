<?php

namespace App\Modules\Academics\Support;

use Illuminate\Support\Str;

/**
 * A preschool section's activity program, stored as text such as
 * "11:00-13:00. Free play 11:00; meeting 11:15; activities 12:05, 12:35; wrap-up 12:55."
 * read as class hours plus a timed list of activities, for the same table layout the subject timetables use.
 */
final class ActivityProgram
{
    private const TIMES = '/^(?<label>.*?)[\s,]*(?<times>\d{1,2}:\d{2}(?:\s*[-–]\s*\d{1,2}:\d{2})?(?:\s*,\s*\d{1,2}:\d{2})*)$/u';

    /**
     * One item per line: split at semicolons and at sentence ends,
     * e.g. "11:00-13:00. Free play 11:00; meeting 11:15." becomes ["11:00-13:00", "Free play 11:00", "Meeting 11:15"].
     *
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        $parts = preg_split('/\s*;\s*|(?<=\.)\s+(?=[A-Z0-9])/', trim((string) $text)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $part): string => Str::ucfirst(rtrim(trim($part), '.')),
            $parts,
        ), fn (string $line): bool => $line !== ''));
    }

    /**
     * The first time range is the class hours ("First batch, 08:00-11:00"). Each other time is one activity row,
     * in time order; an activity ends when the next one starts, and the last one when class ends. Lines without
     * a time are kept as notes.
     *
     * @return array{hours: ?array{label: ?string, start: string, end: string}, rows: list<array{activity: string, start: string, end: ?string}>, notes: list<string>}
     */
    public static function parse(?string $text): array
    {
        $hours = null;
        $rows = [];
        $notes = [];
        foreach (self::lines($text) as $line) {
            if (preg_match(self::TIMES, $line, $match) !== 1) {
                $notes[] = $line;

                continue;
            }
            $label = trim($match['label'], " ,:\t");
            if ($hours === null && preg_match('/^(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})$/', trim($match['times']), $range) === 1) {
                $hours = ['label' => $label !== '' ? Str::ucfirst($label) : null, 'start' => self::time($range[1]), 'end' => self::time($range[2])];

                continue;
            }
            foreach (preg_split('/\s*,\s*/', trim($match['times'])) ?: [] as $time) {
                [$start, $end] = array_pad(preg_split('/\s*[-–]\s*/', $time) ?: [], 2, null);
                $rows[] = ['activity' => Str::ucfirst($label !== '' ? $label : 'Activity'), 'start' => self::time($start), 'end' => $end !== null ? self::time($end) : null];
            }
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['start'], $b['start']));
        foreach ($rows as $index => $row) {
            if ($row['end'] === null) {
                $rows[$index]['end'] = $rows[$index + 1]['start'] ?? $hours['end'] ?? null;
            }
        }

        return ['hours' => $hours, 'rows' => $rows, 'notes' => $notes];
    }

    private static function time(string $value): string
    {
        [$hour, $minute] = explode(':', trim($value));

        return sprintf('%02d:%s', (int) $hour, $minute);
    }
}
