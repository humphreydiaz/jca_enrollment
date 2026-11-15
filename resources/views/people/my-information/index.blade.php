<x-layouts.app title="Student Information">
    @php
        $field = fn ($key, $fallback = '') => old($key, $student?->{$key} ?? $fallback);
        $readOnly = fn ($value) => filled($value) ? $value : 'Not provided';
    @endphp
    <div class="mx-auto max-w-5xl space-y-6" x-data="{ editing: false }">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Student and guardian portal</p>
                <h1 class="mt-2 text-3xl font-black text-brand-navy-900">Student Information</h1>
                <p class="mt-2 text-base text-muted">Personal and family details recorded during enrollment.</p>
            </div>
        </header>

        @if($students->count() > 1)
            <section class="jca-card p-5">
                <label for="student_picker" class="mb-2 block font-bold text-brand-navy-900">Select learner</label>
                <select id="student_picker" class="jca-input" onchange="window.location.href = this.value">
                    @foreach($students as $option)
                        <option value="{{ route('my-information.index', ['student' => $option->id]) }}" @selected($student?->id === $option->id)>{{ $option->full_name }}</option>
                    @endforeach
                </select>
            </section>
        @endif

        @if(!$student)
            <section class="jca-card p-6"><p class="text-muted">No learner profile is currently available for this account.</p></section>
        @else
            <form method="POST" action="{{ route('my-information.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')
                <input type="hidden" name="student_id" value="{{ $student->id }}">

                <section class="jca-card p-5 sm:p-7">
                    <div class="border-b border-border pb-4"><p class="text-xs font-extrabold uppercase tracking-widest text-brand-orange-500">Section 1</p><h2 class="mt-1 text-xl font-black text-brand-navy-900">Learner identity</h2><p class="mt-1 text-sm text-muted">Verified identity fields can only be corrected by the Registrar.</p></div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach(['student_number'=>'Student number','lrn'=>'LRN','first_name'=>'First name','middle_name'=>'Middle name','last_name'=>'Last name','extension_name'=>'Extension name','sex'=>'Sex','birth_date'=>'Birth date'] as $key=>$label)
                            <x-ui.field :for="'info_'.$key" :label="$label"><input id="info_{{ $key }}" class="jca-input bg-slate-50" value="{{ $key === 'birth_date' ? ($student->birth_date?->format('Y-m-d') ?? '') : $student->{$key} }}" readonly></x-ui.field>
                        @endforeach
                        <x-ui.field for="info_age" label="Current age"><input id="info_age" class="jca-input bg-slate-50" value="{{ $student->birth_date?->age ?? '' }}" readonly></x-ui.field>
                        <x-ui.field for="birth_place" label="Place of birth"><input id="birth_place" name="birth_place" class="jca-input read-only:bg-slate-50" value="{{ $field('birth_place') }}" maxlength="255" :readonly="!editing"></x-ui.field>
                    </div>
                </section>

                <section class="jca-card p-5 sm:p-7">
                    <div class="border-b border-border pb-4"><p class="text-xs font-extrabold uppercase tracking-widest text-brand-orange-500">Section 2</p><h2 class="mt-1 text-xl font-black text-brand-navy-900">Learner background and indicators</h2></div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <x-ui.field for="mother_tongue" label="Mother tongue"><input id="mother_tongue" name="mother_tongue" class="jca-input read-only:bg-slate-50" value="{{ $field('mother_tongue') }}" maxlength="100" :readonly="!editing"></x-ui.field>
                        @foreach(['religion'=>'Religion','ip_group'=>'Indigenous Peoples group','is_4ps_recipient'=>'4Ps beneficiary','four_ps_household_id'=>'4Ps household ID','is_learner_with_disability'=>'Learner with disability','disability_type'=>'Type of disability'] as $key=>$label)
                            <x-ui.field :for="'info_'.$key" :label="$label"><input id="info_{{ $key }}" class="jca-input bg-slate-50" value="{{ in_array($key, ['is_4ps_recipient','is_learner_with_disability']) ? ($student->{$key} === null ? 'Not provided' : ($student->{$key} ? 'Yes' : 'No')) : $readOnly($student->{$key}) }}" readonly></x-ui.field>
                        @endforeach
                    </div>
                </section>

                <section class="jca-card p-5 sm:p-7">
                    <div class="border-b border-border pb-4"><p class="text-xs font-extrabold uppercase tracking-widest text-brand-orange-500">Section 3</p><h2 class="mt-1 text-xl font-black text-brand-navy-900">Learner address</h2></div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        @foreach(['current_address'=>'Current address','permanent_address'=>'Permanent address'] as $key=>$label)
                            <x-ui.field :for="$key" :label="$label"><textarea id="{{ $key }}" name="{{ $key }}" rows="3" maxlength="1000" class="jca-input read-only:bg-slate-50" :readonly="!editing">{{ $field($key) }}</textarea></x-ui.field>
                        @endforeach
                        <x-ui.field for="mobile" label="Learner mobile number"><input id="mobile" name="mobile" type="tel" maxlength="30" value="{{ $field('mobile') }}" class="jca-input read-only:bg-slate-50" :readonly="!editing"></x-ui.field>
                    </div>
                </section>

                <section class="jca-card p-5 sm:p-7">
                    <div class="border-b border-border pb-4"><p class="text-xs font-extrabold uppercase tracking-widest text-brand-orange-500">Section 4</p><h2 class="mt-1 text-xl font-black text-brand-navy-900">Parent and guardian information</h2></div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach(['father_first_name'=>"Father's first name",'father_middle_name'=>"Father's middle name",'father_last_name'=>"Father's last name",'father_extension_name'=>"Father's suffix",'mother_first_name'=>"Mother's first name",'mother_middle_name'=>"Mother's middle name",'mother_maiden_last_name'=>"Mother's maiden last name",'mother_extension_name'=>"Mother's suffix"] as $key=>$label)
                            <x-ui.field :for="$key" :label="$label"><input id="{{ $key }}" name="{{ $key }}" maxlength="100" class="jca-input read-only:bg-slate-50" value="{{ $field($key) }}" :readonly="!editing"></x-ui.field>
                        @endforeach
                        @php($displayGuardian = $guardian ?? $enrollment?->guardian)
                        @if($displayGuardian)
                            @foreach(['first_name'=>'Guardian first name','middle_name'=>'Guardian middle name','last_name'=>'Guardian last name','extension_name'=>'Guardian suffix'] as $key=>$label)
                                <x-ui.field :for="'guardian_'.$key" :label="$label"><input id="guardian_{{ $key }}" value="{{ $displayGuardian->{$key} }}" class="jca-input bg-slate-50" readonly></x-ui.field>
                            @endforeach
                            <x-ui.field for="guardian_relationship" label="Relationship to learner"><input id="guardian_relationship" value="{{ $enrollment?->guardian_relationship ?? 'Recorded in family links' }}" class="jca-input bg-slate-50" readonly></x-ui.field>
                            @if($guardian)
                                <x-ui.field for="guardian_mobile" label="Guardian mobile number"><input id="guardian_mobile" name="guardian_mobile" value="{{ old('guardian_mobile', $guardian->mobile) }}" class="jca-input read-only:bg-slate-50" maxlength="30" :readonly="!editing"></x-ui.field>
                                <x-ui.field for="guardian_email" label="Guardian email"><input id="guardian_email" name="guardian_email" type="email" value="{{ old('guardian_email', $guardian->email) }}" class="jca-input read-only:bg-slate-50" maxlength="255" :readonly="!editing"></x-ui.field>
                            @else
                                <x-ui.field for="guardian_mobile_display" label="Guardian mobile number"><input id="guardian_mobile_display" value="{{ $displayGuardian->mobile }}" class="jca-input bg-slate-50" readonly></x-ui.field>
                                <x-ui.field for="guardian_email_display" label="Guardian email"><input id="guardian_email_display" value="{{ $displayGuardian->email }}" class="jca-input bg-slate-50" readonly></x-ui.field>
                            @endif
                        @endif
                    </div>
                </section>

                <section class="jca-card flex flex-wrap items-center justify-between gap-3 p-5 sm:p-7">
                    <p class="text-sm text-muted">Updates to editable fields are recorded in the school's Audit Log.</p>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" x-show="!editing" @click="editing = true" class="rounded-lg bg-brand-navy-900 px-5 py-3 font-bold text-white">Edit Information</button>
                        <button type="button" x-show="editing" x-cloak @click="window.location.reload()" class="rounded-lg border border-border px-5 py-3 font-bold text-brand-navy-900">Cancel</button>
                        <button type="submit" x-show="editing" x-cloak class="rounded-lg bg-brand-navy-900 px-5 py-3 font-bold text-white">Save Changes</button>
                    </div>
                </section>
            </form>
        @endif
    </div>
</x-layouts.app>
