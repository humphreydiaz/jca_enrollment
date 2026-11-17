@props([
    'savedGroup' => null,
    // false while the form has never been saved, so neither Yes nor No starts selected.
    'answered' => true,
])

@php
    // D-057: a listed group is selected; any other saved name shows under "Other (please specify)".
    $other = \App\Modules\People\Support\LearnerProfileOptions::INDIGENOUS_PEOPLES_OTHER;
    $listed = filled($savedGroup) && in_array($savedGroup, \App\Modules\People\Support\LearnerProfileOptions::indigenousPeoplesGroups(), true);
    $answer = (string) old('is_indigenous', filled($savedGroup) ? '1' : ($answered ? '0' : ''));
    $group = (string) old('ip_group', blank($savedGroup) ? '' : ($listed ? $savedGroup : $other));
    $otherName = old('ip_group_other', filled($savedGroup) && ! $listed ? $savedGroup : '');
@endphp

<fieldset {{ $attributes->merge(['class' => 'rounded-xl border border-border p-4']) }} x-data="{ isIndigenous: @js($answer), ipGroup: @js($group) }">
    <legend class="px-1 text-sm font-bold text-ink">Is the learner a member of an Indigenous Cultural Community/Indigenous Peoples group? *</legend>
    <div class="mt-3 flex flex-wrap gap-5">
        <label class="inline-flex items-center gap-2"><input type="radio" name="is_indigenous" value="1" required @checked($answer === '1') x-model="isIndigenous"> <span>Yes</span></label>
        <label class="inline-flex items-center gap-2"><input type="radio" name="is_indigenous" value="0" required @checked($answer === '0') x-model="isIndigenous"> <span>No</span></label>
    </div>
    @error('is_indigenous')<p class="mt-2 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror
    <div class="mt-4 grid gap-4 sm:grid-cols-2" x-show="isIndigenous === '1'">
        <x-ui.field for="ip_group" label="Indigenous Peoples group *">
            <select id="ip_group" name="ip_group" class="jca-input" x-model="ipGroup" :required="isIndigenous === '1'">
                <option value="">Select</option>
                @foreach (\App\Modules\People\Support\LearnerProfileOptions::INDIGENOUS_PEOPLES as $area => $groups)
                    <optgroup label="{{ $area }}">
                        @foreach ($groups as $name)
                            <option value="{{ $name }}" @selected($group === $name)>{{ $name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
                <option value="{{ $other }}" @selected($group === $other)>Other (please specify)</option>
            </select>
        </x-ui.field>
        <div x-show="ipGroup === @js($other)">
            <x-ui.field for="ip_group_other" label="Name of the group *">
                <input id="ip_group_other" name="ip_group_other" value="{{ $otherName }}" maxlength="150" class="jca-input" :required="isIndigenous === '1' && ipGroup === @js($other)">
            </x-ui.field>
        </div>
    </div>
</fieldset>
