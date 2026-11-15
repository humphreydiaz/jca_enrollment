<?php

namespace App\Modules\Academics\Support;

/** Shared validation for class-time inputs on offering and curriculum timetable forms. */
final class ScheduleRules
{
    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'days' => ['nullable', 'array', 'max:6'],
            'days.*' => ['integer', 'between:1,6', 'distinct'],
            'starts_at' => ['nullable', 'date_format:H:i', 'required_with:ends_at'],
            'ends_at' => ['nullable', 'date_format:H:i', 'required_with:starts_at', 'after:starts_at'],
            'schedule_text' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return ['starts_at' => 'start time', 'ends_at' => 'end time', 'days' => 'class days'];
    }
}
