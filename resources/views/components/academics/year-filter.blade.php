@props([
    'action',
    'years',
    'year' => null,
    'currentYear' => null,
    'grades' => null,
    'grade' => null,
    'sections' => null,
    'section' => null,
    'showArchived' => null,
    'hash' => null,
])

{{-- GET filter: works without JavaScript; Alpine only submits on change and clears dependent choices. --}}
<form method="GET" action="{{ $action }}{{ $hash ? '#'.$hash : '' }}"
      x-data
      x-on:change="
        if ($event.target.name === 'year') { $el.querySelectorAll('[data-dependent]').forEach((field) => field.value = ''); }
        if ($event.target.name === 'grade' && $el.querySelector('[name=section]')) { $el.querySelector('[name=section]').value = ''; }
        $el.requestSubmit();
      "
      {{ $attributes->class(['jca-card flex flex-wrap items-end gap-3 p-4']) }}>
    <div class="min-w-[12rem] flex-1 space-y-1.5">
        <label for="filter_year" class="block text-xs font-extrabold uppercase tracking-wider text-muted">School year</label>
        <select id="filter_year" name="year" class="jca-input">
            @foreach ($years as $option)
                <option value="{{ $option->public_id }}" @selected($year?->is($option))>
                    {{ $option->code }}{{ $currentYear?->is($option) ? ' (current)' : ' · '.ucfirst($option->status) }}
                </option>
            @endforeach
        </select>
    </div>

    @if ($grades !== null)
        <div class="min-w-[11rem] flex-1 space-y-1.5">
            <label for="filter_grade" class="block text-xs font-extrabold uppercase tracking-wider text-muted">Grade level</label>
            <select id="filter_grade" name="grade" class="jca-input" data-dependent>
                <option value="">All grade levels</option>
                @foreach ($grades as $option)
                    <option value="{{ $option->public_id }}" @selected($grade?->is($option))>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($sections !== null)
        <div class="min-w-[11rem] flex-1 space-y-1.5">
            <label for="filter_section" class="block text-xs font-extrabold uppercase tracking-wider text-muted">Section</label>
            <select id="filter_section" name="section" class="jca-input" data-dependent>
                <option value="">All sections</option>
                @foreach ($sections->groupBy(fn ($item) => $item->gradeLevel->name) as $gradeName => $gradeSections)
                    @continue($grade && $gradeSections->first()->grade_level_id !== $grade->id)
                    <optgroup label="{{ $gradeName }}">
                        @foreach ($gradeSections as $option)
                            <option value="{{ $option->public_id }}" @selected($section?->is($option))>{{ $option->name }}{{ $option->status !== 'active' ? ' (archived)' : '' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
    @endif

    @if ($showArchived !== null)
        <label class="jca-check pb-2 text-sm">
            <input type="hidden" name="archived" value="0">
            <input type="checkbox" name="archived" value="1" @checked($showArchived)>
            Show archived sections
        </label>
    @endif

    <x-ui.button type="submit" variant="secondary">Show</x-ui.button>
</form>
