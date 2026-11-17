<x-layouts.app :title="$student->full_name">
    @php
        $exitOptions = \App\Modules\People\Support\StudentExitOptions::class;
        $exitLabel = $exitOptions::exitLabel($student->status);
        $transferErrors = $errors->getBag('transferOut');
        $dropErrors = $errors->getBag('dropStudent');
        $openExitForm = $dropErrors->any() ? 'drop' : ($transferErrors->any() ? 'transfer' : '');
        $today = today()->toDateString();
    @endphp

    <div class="space-y-6">
        <x-ui.page-header
            title="Learner record"
            description="The learner's profile, guardians on record, and every application filed for this learner."
            eyebrow="Registrar workspace"
        />

        @if ($exitLabel)
            {{-- Exit banner: the learner has left the school and is not counted as Enrolled. --}}
            <section class="jca-card border-l-4 {{ $student->status === 'dropped' ? 'border-l-brand-red-600' : 'border-l-brand-orange-500' }} p-5 sm:p-6" aria-labelledby="exit-status-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="exit-status-title" class="text-xl font-black text-brand-navy-900">This learner is {{ $exitLabel }}</h2>
                        <p class="mt-1 text-base text-muted">
                            The record is archived. Enrollment history, grades, and documents are kept for reference.
                        </p>
                    </div>
                    <x-ui.status-badge :status="$student->status" :label="$exitLabel" />
                </div>

                <dl class="mt-5 grid gap-4 text-base sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-sm font-semibold text-muted">Record status</dt>
                        <dd class="mt-1 font-bold text-ink">{{ $exitLabel }}</dd>
                    </div>
                    @if ($student->transferred_out_at)
                        <div>
                            <dt class="text-sm font-semibold text-muted">Transfer date</dt>
                            <dd class="mt-1 font-bold text-ink">{{ $student->transferred_out_at->format('F j, Y') }}</dd>
                        </div>
                    @endif
                    @if ($student->archived_at)
                        <div>
                            <dt class="text-sm font-semibold text-muted">Archived on</dt>
                            <dd class="mt-1 font-bold text-ink">{{ $student->archived_at->format('F j, Y g:i A') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($student->archive_reason)
                    <div class="mt-5 rounded-xl border border-border bg-slate-50 p-4">
                        <p class="text-sm font-bold uppercase tracking-wide text-muted">Details recorded</p>
                        <p class="mt-2 whitespace-pre-line text-base leading-7 text-ink">{{ $student->archive_reason }}</p>
                    </div>
                @endif
            </section>
        @endif

        <div class="jca-card p-5 sm:p-6">
            @include('people.registrar.students.partials.record')
        </div>

        @if (! $student->archived_at && $student->status === 'active')
            {{-- Learner exit: Transfer out (SF10) or Drop (DepEd LIS). --}}
            <section class="jca-card p-5 sm:p-6" aria-labelledby="learner-exit-title" x-data="{ exitType: @js($openExitForm) }">
                <h2 id="learner-exit-title" class="text-xl font-black text-brand-navy-900">Learner leaving the school</h2>
                <p class="mt-1 max-w-3xl text-base leading-7 text-muted">
                    Use this only when the learner officially leaves JCA. The learner will be shown as
                    <strong>Transferred Out</strong> or <strong>Dropped</strong> (not Enrolled) and moved to Archived records.
                    Enrollment history, grades, and documents are preserved.
                </p>

                <fieldset class="mt-5">
                    <legend class="jca-label">What happened?</legend>
                    <div class="mt-2 grid gap-3 md:grid-cols-2">
                        <label class="jca-choice">
                            <input type="radio" name="exit_type_choice" value="transfer" x-model="exitType">
                            <span>
                                <span class="block font-bold text-brand-navy-900">Transfer out</span>
                                <span class="mt-0.5 block text-sm text-muted">The learner moves to another school. Release the SF10 (formerly Form 137) school-to-school.</span>
                            </span>
                        </label>
                        <label class="jca-choice">
                            <input type="radio" name="exit_type_choice" value="drop" x-model="exitType">
                            <span>
                                <span class="block font-bold text-brand-navy-900">Drop</span>
                                <span class="mt-0.5 block text-sm text-muted">The learner stops attending. The drop itself is done directly in DepEd LIS.</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                {{-- Transfer out --}}
                <form
                    method="POST"
                    action="{{ route('people.registrar.students.transfer-out', $student) }}"
                    class="mt-6 space-y-5 border-t border-border pt-6"
                    x-show="exitType === 'transfer'"
                    x-cloak
                    data-confirm="Mark {{ $student->full_name }} as Transferred Out? The record will be archived."
                    data-loading-submit
                >
                    @csrf
                    <h3 class="text-lg font-black text-brand-navy-900">Transfer out &amp; SF10 release</h3>

                    @if ($transferErrors->any())
                        <div class="rounded-lg border border-brand-red-600 bg-red-50 p-4 text-brand-red-600" role="alert">
                            <p class="font-bold">Please correct the following:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                                @foreach ($transferErrors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="receiving_school" class="jca-label jca-required">Receiving school</label>
                            <input id="receiving_school" name="receiving_school" value="{{ old('receiving_school') }}" maxlength="255" class="jca-input" placeholder="Name of the school the learner is transferring to">
                            @if ($transferErrors->has('receiving_school'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $transferErrors->first('receiving_school') }}</p>@endif
                        </div>
                        <div>
                            <label for="receiving_school_id" class="jca-label">DepEd school ID <span class="font-normal text-muted">(optional)</span></label>
                            <input id="receiving_school_id" name="receiving_school_id" value="{{ old('receiving_school_id') }}" maxlength="20" class="jca-input" inputmode="numeric">
                            @if ($transferErrors->has('receiving_school_id'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $transferErrors->first('receiving_school_id') }}</p>@endif
                        </div>
                        <div>
                            <label for="transfer_effective_on" class="jca-label jca-required">Transfer date</label>
                            <input id="transfer_effective_on" type="date" name="effective_on" value="{{ old('effective_on', $today) }}" max="{{ $today }}" class="jca-input">
                            @if ($transferErrors->has('effective_on'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $transferErrors->first('effective_on') }}</p>@endif
                        </div>
                        <div class="md:col-span-2">
                            <label for="receiving_school_address" class="jca-label">Receiving school address <span class="font-normal text-muted">(optional)</span></label>
                            <input id="receiving_school_address" name="receiving_school_address" value="{{ old('receiving_school_address') }}" maxlength="500" class="jca-input">
                        </div>
                    </div>

                    <fieldset>
                        <legend class="jca-label jca-required">How is the SF10 (formerly Form 137) being released?</legend>
                        <p class="mt-1 text-sm text-muted">The SF10 is a school-to-school transaction. If the receiving school is too far, the student or guardian hand-carries it.</p>
                        <div class="mt-2 grid gap-3 lg:grid-cols-3">
                            @foreach ($exitOptions::SF10_RELEASE_METHODS as $method => $methodLabel)
                                <label class="jca-choice">
                                    <input type="radio" name="sf10_release_method" value="{{ $method }}" @checked(old('sf10_release_method') === $method)>
                                    <span class="text-base font-semibold text-ink">{{ $methodLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if ($transferErrors->has('sf10_release_method'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $transferErrors->first('sf10_release_method') }}</p>@endif
                    </fieldset>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="sf10_released_on" class="jca-label jca-required">SF10 release date</label>
                            <input id="sf10_released_on" type="date" name="sf10_released_on" value="{{ old('sf10_released_on', $today) }}" max="{{ $today }}" class="jca-input">
                            @if ($transferErrors->has('sf10_released_on'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $transferErrors->first('sf10_released_on') }}</p>@endif
                        </div>
                        <div>
                            <label for="sf10_released_to" class="jca-label">Hand-carried by <span class="font-normal text-muted">(required if hand-carried)</span></label>
                            <input id="sf10_released_to" name="sf10_released_to" value="{{ old('sf10_released_to') }}" maxlength="150" class="jca-input" placeholder="Name of student or guardian">
                            @if ($transferErrors->has('sf10_released_to'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $transferErrors->first('sf10_released_to') }}</p>@endif
                        </div>
                    </div>

                    <div>
                        <label for="transfer_reason" class="jca-label jca-required">Reason or remarks</label>
                        <textarea id="transfer_reason" name="reason" rows="3" maxlength="1000" class="jca-input" placeholder="e.g. Family relocated to another province">{{ $transferErrors->any() ? old('reason') : '' }}</textarea>
                        @if ($transferErrors->has('reason'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $transferErrors->first('reason') }}</p>@endif
                    </div>

                    <label class="flex items-start gap-3 text-base text-ink">
                        <input type="checkbox" name="confirm_transfer" value="1" class="mt-1 size-4 accent-brand-navy-700" @checked(old('confirm_transfer'))>
                        <span>I confirm that this learner is officially transferring out and that the record will be archived.</span>
                    </label>
                    @if ($transferErrors->has('confirm_transfer'))<p class="text-sm font-semibold text-brand-red-600">{{ $transferErrors->first('confirm_transfer') }}</p>@endif

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="jca-button jca-button-primary" data-loading-text="Saving…">Mark as Transferred Out</button>
                        <button type="button" class="jca-button jca-button-secondary" @click="exitType = ''">Cancel</button>
                    </div>
                </form>

                {{-- Drop --}}
                <form
                    method="POST"
                    action="{{ route('people.registrar.students.drop', $student) }}"
                    class="mt-6 space-y-5 border-t border-border pt-6"
                    x-show="exitType === 'drop'"
                    x-cloak
                    data-confirm="Mark {{ $student->full_name }} as Dropped? The record will be archived."
                    data-loading-submit
                >
                    @csrf
                    <h3 class="text-lg font-black text-brand-navy-900">Drop learner</h3>

                    <div class="rounded-xl border border-brand-gold-500 bg-warning-100 p-4 text-base text-warning-800">
                        <p class="font-bold">Drop the learner in DepEd LIS first.</p>
                        <p class="mt-1">Dropping is done directly in the DepEd Learner Information System (LIS). Recording it here only marks the learner as Dropped in the JCA system so they are no longer shown as Enrolled.</p>
                    </div>

                    @if ($dropErrors->any())
                        <div class="rounded-lg border border-brand-red-600 bg-red-50 p-4 text-brand-red-600" role="alert">
                            <p class="font-bold">Please correct the following:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                                @foreach ($dropErrors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="drop_effective_on" class="jca-label jca-required">Drop date</label>
                            <input id="drop_effective_on" type="date" name="effective_on" value="{{ $dropErrors->any() ? old('effective_on', $today) : $today }}" max="{{ $today }}" class="jca-input">
                            @if ($dropErrors->has('effective_on'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $dropErrors->first('effective_on') }}</p>@endif
                        </div>
                        <div>
                            <label for="drop_category" class="jca-label jca-required">Kind of drop</label>
                            <select id="drop_category" name="drop_category" class="jca-input" required>
                                <option value="">Select</option>
                                @foreach (\App\Modules\People\Support\StudentExitOptions::DROP_CATEGORIES as $value => $label)
                                    <option value="{{ $value }}" @selected($dropErrors->any() && old('drop_category') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if ($dropErrors->has('drop_category'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $dropErrors->first('drop_category') }}</p>@endif
                        </div>
                    </div>

                    <div>
                        <label for="drop_reason" class="jca-label jca-required">Reason for dropping</label>
                        <textarea id="drop_reason" name="reason" rows="3" maxlength="1000" class="jca-input" placeholder="e.g. Learner stopped attending since August">{{ $dropErrors->any() ? old('reason') : '' }}</textarea>
                        @if ($dropErrors->has('reason'))<p class="mt-1 text-sm font-semibold text-brand-red-600">{{ $dropErrors->first('reason') }}</p>@endif
                    </div>

                    <label class="flex items-start gap-3 text-base text-ink">
                        <input type="checkbox" name="lis_dropped" value="1" class="mt-1 size-4 accent-brand-navy-700" @checked($dropErrors->any() && old('lis_dropped'))>
                        <span>The learner has already been dropped directly in DepEd LIS.</span>
                    </label>
                    @if ($dropErrors->has('lis_dropped'))<p class="text-sm font-semibold text-brand-red-600">{{ $dropErrors->first('lis_dropped') }}</p>@endif

                    <label class="flex items-start gap-3 text-base text-ink">
                        <input type="checkbox" name="confirm_drop" value="1" class="mt-1 size-4 accent-brand-navy-700" @checked($dropErrors->any() && old('confirm_drop'))>
                        <span>I confirm that this learner is officially dropped and that the record will be archived.</span>
                    </label>
                    @if ($dropErrors->has('confirm_drop'))<p class="text-sm font-semibold text-brand-red-600">{{ $dropErrors->first('confirm_drop') }}</p>@endif

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="jca-button jca-button-danger" data-loading-text="Saving…">Mark as Dropped</button>
                        <button type="button" class="jca-button jca-button-secondary" @click="exitType = ''">Cancel</button>
                    </div>
                </form>
            </section>
        @endif
    </div>
</x-layouts.app>
