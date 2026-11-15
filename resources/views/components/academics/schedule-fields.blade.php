@props([
    'idPrefix',
    'record' => null,
    'compact' => false,
    'useOld' => false,
])

@php
    $schedule = $record ? \App\Modules\Academics\Support\ClassSchedule::fromRecord($record) : null;
    $selectedDays = $schedule?->days ?? \App\Modules\Academics\Support\ClassSchedule::SCHOOL_WEEK;
    $freeText = $record && $schedule === null && filled($record->schedule_text) ? $record->schedule_text : null;
    $startsAt = $schedule?->startsAt;
    $endsAt = $schedule?->endsAt;
    if ($useOld) {
        $selectedDays = array_map('intval', (array) old('days', $selectedDays));
        $startsAt = old('starts_at', $startsAt);
        $endsAt = old('ends_at', $endsAt);
    }
@endphp

<div {{ $attributes->class(['grid gap-3', $compact ? 'grid-cols-2' : 'grid-cols-2 md:grid-cols-[minmax(0,1fr)_10rem_10rem]']) }}>
    <fieldset @class(['min-w-0', 'col-span-2' => true, 'md:col-span-1' => ! $compact])>
        <legend class="mb-2 block text-sm font-bold text-ink">Class days</legend>
        <div class="flex flex-wrap gap-1.5">
            @foreach (\App\Modules\Academics\Support\ClassSchedule::SHORT_WEEKDAYS as $number => $short)
                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-border bg-white px-2.5 py-1.5 text-sm font-semibold has-[:checked]:border-brand-navy-700 has-[:checked]:bg-brand-blue-100 has-[:checked]:text-brand-navy-900">
                    <input type="checkbox" name="days[]" value="{{ $number }}" class="size-4 accent-brand-navy-700" @checked(in_array($number, $selectedDays, true))>
                    <span>{{ $short }}<span class="sr-only"> ({{ \App\Modules\Academics\Support\ClassSchedule::WEEKDAYS[$number] }})</span></span>
                </label>
            @endforeach
        </div>
    </fieldset>
    <div class="space-y-2">
        <label for="{{ $idPrefix }}_starts" class="block text-sm font-bold text-ink">From</label>
        <input id="{{ $idPrefix }}_starts" name="starts_at" type="time" step="300" value="{{ $startsAt }}" class="jca-input">
    </div>
    <div class="space-y-2">
        <label for="{{ $idPrefix }}_ends" class="block text-sm font-bold text-ink">To</label>
        <input id="{{ $idPrefix }}_ends" name="ends_at" type="time" step="300" value="{{ $endsAt }}" class="jca-input">
    </div>
    @if ($freeText)
        <p class="col-span-2 text-xs text-muted md:col-span-3">Current note: “{{ $freeText }}”. Enter a start and end time to replace it with a checked class time.</p>
    @endif
</div>
