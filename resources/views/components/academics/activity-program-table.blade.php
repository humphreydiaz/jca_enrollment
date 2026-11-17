@props(['schedule', 'name'])

@php
    $program = \App\Modules\Academics\Support\ActivityProgram::parse($schedule);
    $hours = $program['hours'];
    $rows = $program['rows'];
    $notes = $program['notes'];

    // Display only: 24-hour and existing AM/PM times become 12-hour AM/PM.
    $formatTime = static function ($time): string {
        $value = trim((string) $time);

        if (preg_match('/^(\d{1,2}):([0-5]\d)\s*(AM|PM)$/i', $value, $m)) {
            $hour = (int) $m[1];
            if ($hour >= 1 && $hour <= 12) {
                return sprintf('%d:%s %s', $hour, $m[2], strtoupper($m[3]));
            }
        }

        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $value, $m)) {
            $hour = (int) $m[1];
            return sprintf('%d:%s %s', ($hour % 12) ?: 12, $m[2], $hour >= 12 ? 'PM' : 'AM');
        }

        return $value;
    };

    // Some older Junior/Senior Kinder schedules were saved as one text line:
    // "Class Schedule: 8:00 AM - 10:25 AM 8:00 AM - Meeting 8:15 AM - Activity 1 ..."
    // If the standard parser cannot produce rows, split the time-first entries.
    // This changes the display only, never the original schedule/database.
    if ($rows === [] && filled($schedule)) {
        $source = html_entity_decode((string) $schedule, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $matches = [];
        preg_match_all('/(?<!\d)(?:[01]?\d|2[0-3]):[0-5]\d(?:\s*(?:AM|PM))?(?!\d)/i', $source, $matches, PREG_OFFSET_CAPTURE);
        $times = $matches[0] ?? [];

        $hasHeader = (bool) preg_match('/\bclass\s*(?:schedule|hours)\s*:/i', $source);
        $startsWithTimes = (bool) preg_match('/^\s*\d{1,2}:[0-5]\d/i', $source);

        if (count($times) >= 4 && ($hasHeader || $startsWithTimes)) {
            $classStart = $times[0][0];
            $classEnd = $times[1][0];
            $parsed = [];

            for ($i = 2, $count = count($times); $i < $count; $i++) {
                $activityStart = $times[$i][1] + strlen($times[$i][0]);
                $activityEnd = $i + 1 < $count ? $times[$i + 1][1] : strlen($source);
                $label = substr($source, $activityStart, $activityEnd - $activityStart);
                // Strip separators, including corrupted dash characters, from edges.
                $label = trim((string) preg_replace('/^[^A-Za-z0-9]+|[^A-Za-z0-9]+$/u', '', trim($label)));

                if ($label === '') {
                    continue;
                }

                $start = $times[$i][0];
                $end = $i + 1 < $count ? $times[$i + 1][0] : $classEnd;

                $parsed[] = [
                    'activity' => $label,
                    'start' => $start,
                    'end' => $formatTime($start) === $formatTime($end) ? null : $end,
                ];
            }

            if ($parsed !== []) {
                $hours = ['label' => 'Class hours', 'start' => $classStart, 'end' => $classEnd];
                $rows = $parsed;
                $notes = [];
            }
        }
    }
@endphp

<div {{ $attributes }}>
    @if ($hours)
        <p class="text-base font-normal text-brand-navy-900">
            {{ ! empty($hours['label']) ? $hours['label'].':' : 'Class hours:' }}
            <span class="font-normal tabular-nums">
                {{ $formatTime($hours['start']) }} &ndash; {{ $formatTime($hours['end']) }}
            </span>
        </p>
    @endif

    @if ($rows !== [])
        <div class="mt-2 w-full overflow-x-auto">
            <table class="w-full border-collapse text-base">
                <caption class="sr-only">{{ $name }} activity program</caption>
                <thead class="text-left font-bold text-brand-navy-900">
                    <tr>
                        <th scope="col" class="py-2">Activity</th>
                        <th scope="col" class="py-2 text-right">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="py-2 font-normal">{{ $row['activity'] }}</td>
                            <td class="py-2 text-right font-normal tabular-nums">
                                {{ $formatTime($row['start']) }}
                                @if (! empty($row['end']))
                                    &ndash; {{ $formatTime($row['end']) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @foreach ($notes as $note)
        <p class="mt-2 text-base font-normal text-brand-navy-900">{{ $note }}</p>
    @endforeach
</div>
