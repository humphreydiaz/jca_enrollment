<x-layouts.app title="Family links">
    @if (session('granted_access'))
        @php $slip = session('granted_access'); @endphp
        <x-slot:printSlip>
            <x-print.access-slip
                :slip="$slip"
                title="Student account slip"
                steps-title="How to sign in"
                next-step="Use the student pages to see grades, class schedule, and announcements. The parent or guardian keeps their own account and password."
            />
        </x-slot:printSlip>
    @endif
    <div class="space-y-6">
        <x-ui.page-header
            eyebrow="Registrar workspace"
            title="Family links"
            description="Manage student accounts and parent/guardian relationships for Nursery through Grade 10."
        />
        {{-- Junior high account status guidance; actions below operate on existing learner records. --}}
        <section class="rounded-xl border border-brand-navy-700/15 bg-brand-blue-100/40 px-4 py-3 text-sm text-brand-navy-900">
            <p class="font-bold">Grade 7–10: separate student and parent logins</p>
            <p class="mt-1 text-muted">
                Student enrollment remains student-managed. Parent/guardian accounts are linked to
                the learner's record for authorized portal access. Verify identity before linking.
            </p>
        </section>

        {{-- Granted account --}}
        @if (session('granted_access'))
            @php $access = session('granted_access'); @endphp
            <section class="rounded-2xl border-2 border-success-700 bg-emerald-50 p-5 sm:p-7" aria-labelledby="granted-title">
                <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-success-700">
                    Display once — give directly to the learner or parent
                </p>
                <h2 id="granted-title" class="mt-2 text-2xl font-black text-brand-navy-900">
                    Student account issued
                </h2>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold text-muted">Student</dt>
                        <dd class="font-black text-ink">{{ $access['learner_name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-muted">Expires</dt>
                        <dd class="font-bold text-ink">{{ $access['expires_at'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-muted">Student ID</dt>
                        <dd class="select-all font-mono text-xl font-black tracking-wide text-brand-navy-900">
                            {{ $access['student_number'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-muted">Temporary password</dt>
                        <dd class="select-all font-mono text-xl font-black tracking-wide text-brand-navy-900">
                            {{ $access['temporary_password'] }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-5 text-sm leading-6 text-ink">
                    The student signs in using their Student ID and temporary password, then sets a new password.
                    The parent or guardian uses a separate portal account.
                </p>
                <button type="button" class="jca-button jca-button-secondary mt-4 print:hidden" onclick="window.print()">
                    Print slip
                </button>
            </section>
        @endif
        {{-- Search --}}
        <form method="GET"
              action="{{ route('people.admin.index') }}"
              role="search"
              class="jca-card flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[14rem] flex-1 space-y-1.5">
                <label for="family_search" class="block text-xs font-extrabold uppercase tracking-wider text-muted">
                    Find a learner or parent
                </label>
                <input id="family_search"
                       type="search"
                       name="search"
                       value="{{ $search }}"
                       maxlength="100"
                       autocomplete="off"
                       placeholder="Learner name, Student ID, or parent name"
                       class="jca-input">
            </div>
            <x-ui.button type="submit">
                <x-ui.icon name="search" size="size-4" />
                Search
            </x-ui.button>
            @if ($search !== '')
                <a href="{{ route('people.admin.index') }}" class="jca-button-secondary">
                    Clear
                </a>
            @endif
        </form>
        {{-- Learners --}}
        <section class="space-y-4" aria-labelledby="learners-title">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="learners-title" class="text-lg font-black text-brand-navy-900">
                    Learners
                </h2>
                <p class="text-sm font-semibold text-muted">
                    {{ $learners->total() }}
                    {{ Str::plural('learner', $learners->total()) }}
                    {{ $search !== '' ? ' for “'.$search.'”' : '' }}
                </p>
            </div>
            @forelse ($learners as $learner)
                @php
                    $current = $learner->enrollments->first(
                        fn ($enrollment) => ! in_array(
                            $enrollment->status,
                            $terminal,
                            true
                        )
                    ) ?? $learner->enrollments->first();
                    $guardianOwned = $current
                        && ! $current->gradeLevel?->allowsStudentAccountByDefault();
                    $parentLinks = $learner->guardianLinks->filter(
                        fn ($link) => $link->guardian?->user_id !== null
                    );
                    $otherLinks = $learner->guardianLinks->filter(
                        fn ($link) => $link->guardian?->user_id === null
                    );
                    $linkedGuardianIds = $learner->guardianLinks
                        ->pluck('guardian_id')
                        ->all();

                    $canGrant = ! $learner->user
                        && filled($learner->student_number)
                        && $current
                        && ! in_array($current->status, $terminal, true);
                    $form = 'sibling-'.$learner->public_id;
                    $siblingErrors = $errors->getBag($form);
                    $choices = session('sibling_choices')[$learner->public_id] ?? [];
                    $isJuniorHigh = $current && in_array(
                        $current->gradeLevel?->code,
                        ['G7', 'G8', 'G9', 'G10'],
                        true
                    );
                    $parentLinkErrors = old('link_for') === $learner->public_id
                        && $errors->getBag('default')->any();
                @endphp
                <article class="jca-card overflow-hidden" aria-labelledby="learner-{{ $learner->public_id }}">
                    {{-- Learner header --}}
                    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4 sm:px-6">
                        <div>
                            <h3 id="learner-{{ $learner->public_id }}" class="text-lg font-black text-brand-navy-900">
                                {{ $learner->full_name }}
                            </h3>
                            <p class="text-sm text-muted">
                                <span class="font-mono font-bold text-ink">
                                    {{ $learner->student_number ?: 'No Student ID yet' }}
                                </span>
                                @if ($current)
                                    · {{ $current->gradeLevel?->name }}
                                    · {{ $current->academicYear?->name }}
                                    · {{ Str::headline($current->status) }}
                                @endif
                            </p>
                        </div>
                        @if ($current)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-extrabold {{ $guardianOwned ? 'bg-brand-gold-500/25 text-brand-navy-900' : 'bg-brand-blue-100 text-brand-navy-700' }}">
                                {{ $guardianOwned ? 'Parent-managed grade' : 'Student-managed grade' }}
                            </span>
                        @endif
                    </header>
                    <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        {{-- LEFT: Linked accounts --}}
                        <div>
                            <h4 class="text-xs font-extrabold uppercase tracking-wider text-brand-navy-700">
                                Student and Parent/Guardian portal accounts
                            </h4>
                            <ul class="mt-2 divide-y divide-border rounded-xl border border-border text-sm">
                                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
                                    <span>
                                        <span class="font-bold text-brand-navy-900">
                                            Student
                                        </span>
                                        <span class="block text-xs text-muted">
                                            {{ $learner->user ? $learner->user->name : 'No student account' }}
                                        </span>
                                    </span>
                                    @if ($learner->user)
                                        <span class="rounded-full px-2 py-0.5 text-xs font-extrabold {{ $learner->user->must_change_password ? 'bg-warning-100 text-warning-800' : 'bg-success-50 text-success-700' }}">
                                            {{ $learner->user->must_change_password ? 'Waiting for first sign-in' : 'Active' }}
                                        </span>
                                    @else
                                        <span class="text-xs font-semibold text-muted">—</span>
                                    @endif
                                </li>
                                @forelse ($parentLinks as $link)
                                    <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
                                        <span>
                                            <span class="font-bold text-brand-navy-900">
                                                {{ $link->guardian->full_name }}
@if ($isJuniorHigh && filled($link->guardian->parent_login_id))
    <span class="mt-1 block font-mono text-xs font-bold text-brand-navy-700">
        Parent Login ID: {{ $link->guardian->parent_login_id }}
    </span>
@endif
                                            </span>
                                            <span class="block text-xs text-muted">
                                                {{ $relationships[$link->relationship_type] ?? Str::headline($link->relationship_type) }}
                                                · Separate Parent/Guardian login
                                            </span>
                                        </span>
                                        @if (! $link->verified_at)
                                            <form method="POST"
                                                  action="{{ route('people.admin.links.verify', ['guardian' => $link->guardian, 'student' => $learner]) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="rounded-lg border border-brand-navy-700/30 px-3 py-1 text-xs font-bold text-brand-navy-700 hover:bg-brand-blue-100">
                                                    Verify
                                                </button>
                                            </form>
                                        @elseif ($link->may_manage_enrollment)
                                            <span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-extrabold text-success-700">
                                                Verified
                                            </span>
                                        @else
                                            <span class="rounded-full bg-canvas px-2 py-0.5 text-xs font-extrabold text-muted">
                                                Verified · View Only
                                            </span>
                                        @endif
                                    </li>
                                @empty
                                    <li class="px-3 py-2.5 text-xs text-muted">
                                        No parent/guardian portal account is connected.
                                    </li>
                                @endforelse
                            </ul>
                            @if ($otherLinks->isNotEmpty())
                                <div class="mt-3 rounded-lg border border-border bg-canvas p-3">
                                    <p class="text-xs font-bold text-brand-navy-900">
                                        Guardians on record without a portal account
                                    </p>
                                    <ul class="mt-2 space-y-1 text-xs text-muted">
                                        @foreach ($otherLinks as $link)
                                            @if ($link->guardian)
                                                <li>
                                                    {{ $link->guardian->full_name }}
@if ($isJuniorHigh && filled($link->guardian->parent_login_id))
    <span class="mt-1 block font-mono text-xs font-bold text-brand-navy-700">
        Parent Login ID: {{ $link->guardian->parent_login_id }}
    </span>
@endif
                                                    · {{ $relationships[$link->relationship_type] ?? Str::headline($link->relationship_type) }}
                                                    · No Account Yet
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                        {{-- RIGHT: Actions --}}
                        <div class="space-y-3">
                            {{-- 1. Grant Student Account --}}
                            @if ($canGrant)
                                <details class="rounded-xl border border-border"
                                    @if (
                                        $errors->hasAny(['reason', 'requested_in_person', 'student'])
                                        && old('grant_for') === $learner->public_id
                                    ) open @endif>
                                    <summary class="cursor-pointer list-none px-4 py-3 font-bold text-brand-navy-700 hover:bg-canvas">
                                        + Grant student account
                                    </summary>
                                    <form method="POST"
                                          action="{{ route('people.family.grant-account', $learner) }}"
                                          class="space-y-3 border-t border-border p-4">
                                        @csrf
                                        <input type="hidden"
                                               name="grant_for"
                                               value="{{ $learner->public_id }}">
                                        <p class="text-sm text-muted">
                                            {{ $guardianOwned ? 'For a parent who wants their child to have a separate login.' : 'This learner has no Student login yet.' }}
                                            The parent's account does not change.
                                        </p>
                                        <label class="jca-label">
                                            Who asked, and why
                                            <textarea name="reason"
                                                      required
                                                      minlength="10"
                                                      maxlength="500"
                                                      rows="2"
                                                      class="jca-input">{{ old('grant_for') === $learner->public_id ? old('reason') : '' }}</textarea>
                                        </label>
                                        @if (old('grant_for') === $learner->public_id)
                                            @foreach (['reason', 'requested_in_person', 'student'] as $field)
                                                @error($field)
                                                    <p class="text-sm text-red-700">
                                                        {{ $message }}
                                                    </p>
                                                @enderror
                                            @endforeach
                                        @endif
                                        <label class="jca-check text-sm">
                                            <input type="checkbox"
                                                   name="requested_in_person"
                                                   value="1"
                                                   required>
                                            The parent or guardian asked for this in person.
                                        </label>
                                        <x-ui.button type="submit">
                                            Grant and show slip
                                        </x-ui.button>
                                    </form>
                                </details>
                            @endif
                            {{-- 2. Existing parent account for siblings --}}
                            <details class="rounded-xl border border-border"
                                     @if ($siblingErrors->any() || $choices) open @endif>
                                <summary class="cursor-pointer list-none px-4 py-3 font-bold text-brand-navy-700 hover:bg-canvas">
                                    + Connect a parent who already has an account
                                    <span class="font-semibold text-muted">
                                        (siblings)
                                    </span>
                                </summary>
                                <form method="POST"
                                      action="{{ route('people.family.connect-guardian', $learner) }}"
                                      class="space-y-3 border-t border-border p-4">
                                    @csrf
                                    <p class="text-sm text-muted">
                                        Connect the parent account of another child
                                        to this learner after identity verification.
                                    </p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="jca-label">
                                            Other child's Student ID
                                            <input name="sibling_student_number"
                                                   required
                                                   maxlength="30"
                                                   class="jca-input font-mono"
                                                   placeholder="JCA-26-123"
                                                   value="{{ $siblingErrors->any() || $choices ? old('sibling_student_number') : '' }}">
                                        </label>
                                        <label class="jca-label">
                                            Relationship to {{ $learner->first_name }}
                                            <select name="relationship_type"
                                                    required
                                                    class="jca-input">
                                                <option value="">Select</option>
                                                @foreach ($relationships as $value => $label)
                                                    <option value="{{ $value }}"
                                                        @selected(($siblingErrors->any() || $choices) && old('relationship_type') === $value)>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>
                                    @if ($choices)
                                        <fieldset class="space-y-1.5">
                                            <legend class="text-sm font-bold text-brand-navy-900">
                                                Which parent is here?
                                            </legend>
                                            @foreach ($choices as $choice)
                                                <label class="jca-check text-sm">
                                                    <input type="radio"
                                                           name="guardian"
                                                           value="{{ $choice['id'] }}"
                                                           required>
                                                    {{ $choice['name'] }}
                                                </label>
                                            @endforeach
                                        </fieldset>
                                    @endif
                                    @foreach ($siblingErrors->all() as $message)
                                        <p class="text-sm text-red-700">
                                            {{ $message }}
                                        </p>
                                    @endforeach
                                    <label class="jca-check text-sm">
                                        <input type="checkbox"
                                               name="confirmed_in_person"
                                               value="1"
                                               required>
                                        I checked the parent's identity in person.
                                    </label>
                                    <x-ui.button type="submit" variant="secondary">
                                        Connect parent
                                    </x-ui.button>
                                </form>
                            </details>
                            {{-- Grade 7-10 account readiness, based only on existing records. --}}
                            @if ($isJuniorHigh)
                                <div class="rounded-xl border border-border bg-canvas p-3 text-xs text-muted">
                                    <p class="font-bold text-brand-navy-900">Grade 7–10 account status</p>
                                    @if (! $learner->user && $parentLinks->isEmpty())
                                        <p class="mt-1">Student and Parent/Guardian portal accounts are not connected yet.</p>
                                    @elseif (! $learner->user)
                                        <p class="mt-1">Parent account connected. Student login still needs issuance.</p>
                                    @elseif ($parentLinks->isEmpty())
                                        <p class="mt-1">Student account exists. Parent/Guardian portal account still needs to be connected.</p>
                                    @else
                                        <p class="mt-1">Student and Parent/Guardian portal accounts are connected. Check verification status on the left.</p>
                                    @endif
                                </div>
                            @endif
                            {{-- 3. Grade 7-10 Parent/Guardian Linking --}}
                            @if ($isJuniorHigh && ($parentLinks->isEmpty() || $parentLinkErrors))
                                <details class="rounded-xl border border-border"
                                         @if ($parentLinkErrors) open @endif>
                                    <summary class="cursor-pointer list-none px-4 py-3 font-bold text-brand-navy-700 hover:bg-canvas">
                                        + Link Parent/Guardian Account
                                    </summary>
                                    <form method="POST"
                                          action="{{ route('people.family.link-parent', $learner) }}"
                                          class="space-y-3 border-t border-border p-4">
                                        @csrf
                                        <input type="hidden"
                                               name="link_for"
                                               value="{{ $learner->public_id }}">
                                        <p class="text-sm text-muted">
                                            Select a parent/guardian for {{ $learner->full_name }}.
                                            All names can be selected. Student enrollment remains student-managed.
                                        </p>
                                        <label class="jca-label">
                                            Parent/Guardian on Record
                                            <select name="guardian_public_id"
                                                    class="jca-input"
                                                    required>
                                                <option value="">
                                                    Select Parent/Guardian
                                                </option>
                                                @foreach ($availableGuardians as $guardian)
                                                    @php
                                                        $alreadyLinked = in_array($guardian->id, $linkedGuardianIds, true);
                                                        $hasPortalAccount = $guardian->user_id !== null;
                                                    @endphp
                                                    <option
                                                        value="{{ $guardian->public_id }}"
                                                        @selected(
                                                            old('link_for') === $learner->public_id
                                                            && old('guardian_public_id') === $guardian->public_id
                                                        )
                                                    >
                                                        {{ $guardian->full_name }}
                                                        @if ($alreadyLinked)
                                                            — Already Linked
                                                        @elseif ($hasPortalAccount)
                                                            — Has Account
                                                        @else
                                                            — No Account Yet
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <p class="text-xs text-muted">
                                            All available parent/guardian records are shown.
                                            Parents marked “No Account Yet” need portal access first;
                                            “Already Linked” parents do not need to be linked again.
                                            Verify the parent's identity and relationship in person.
                                        </p>
                                        <label class="jca-label">
                                            Relationship to {{ $learner->first_name }}
                                            <select name="relationship_type"
                                                    class="jca-input"
                                                    required>
                                                <option value="">
                                                    Select Relationship
                                                </option>
                                                @foreach ($relationships as $value => $label)
                                                    <option value="{{ $value }}"
                                                        @selected(
                                                            old('link_for') === $learner->public_id
                                                            && old('relationship_type') === $value
                                                        )
                                                    >
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="jca-check text-sm">
                                            <input type="checkbox"
                                                   name="confirmed_in_person"
                                                   value="1"
                                                   required>
                                            I verified the parent's identity and
                                            relationship to this learner in person.
                                        </label>
                                        @if ($parentLinkErrors)
                                            @foreach ($errors->getBag('default')->all() as $message)
                                                <p class="text-sm text-red-700">
                                                    {{ $message }}
                                                </p>
                                            @endforeach
                                        @endif
                                        <x-ui.button type="submit">
                                            Confirm Parent Link
                                        </x-ui.button>
                                    </form>
                                </details>
                            @endif
                            {{-- 4. Repair Tools - Always last --}}
                            @if (auth()->user()->hasRole('admin'))
                                @php
                                    $canRemove = $learner->status === 'active'
                                        && ! $learner->user
                                        && $learner->enrollments->isEmpty()
                                        && $learner->guardian_links_count === 0;
                                    $logins = $loginOptions[$learner->id] ?? collect();
                                @endphp
                                @if ($learner->user || $canRemove || $logins->isNotEmpty())
                                    <details class="rounded-xl border border-red-200">
                                        <summary class="cursor-pointer list-none px-4 py-3 text-sm font-bold text-brand-red-600 hover:bg-red-50">
                                            Repair tools (Admin)
                                        </summary>
                                        <div class="space-y-4 border-t border-red-200 p-4">
                                            @if ($learner->user)
                                                <form method="POST"
                                                      action="{{ route('people.admin.students.account.disconnect', $learner) }}"
                                                      class="space-y-2">
                                                    @csrf
                                                    @method('DELETE')
                                                    <p class="text-sm font-bold text-brand-navy-900">
                                                        Disconnect the Student account
                                                    </p>
                                                    <label class="jca-label">
                                                        Reason
                                                        <textarea name="reason"
                                                                  required
                                                                  minlength="10"
                                                                  maxlength="2000"
                                                                  rows="2"
                                                                  class="jca-input"></textarea>
                                                    </label>
                                                    <p class="text-xs text-muted">
                                                        The login is signed out.
                                                        Learner records and history remain.
                                                    </p>
                                                    <x-ui.button type="submit" variant="danger">
                                                        Disconnect
                                                    </x-ui.button>
                                                </form>
                                            @endif
                                            @if (! $learner->user && $logins->isNotEmpty())
                                                <form method="POST"
                                                      action="{{ route('people.admin.students.account', $learner) }}"
                                                      class="space-y-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <p class="text-sm font-bold text-brand-navy-900">
                                                        Connect an existing Student login
                                                    </p>
                                                    <p class="text-xs text-muted">
                                                        Only for a Student login created
                                                        outside the normal walk-in process.
                                                    </p>
                                                    <label class="jca-label">
                                                        Login
                                                        <select name="user_public_id"
                                                                required
                                                                class="jca-input">
                                                            <option value="">Select</option>
                                                            @foreach ($logins as $login)
                                                                <option value="{{ $login->public_id }}">
                                                                    {{ $login->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label class="jca-label">
                                                        Elementary exception reason
                                                        (Nursery–Grade 6 only)
                                                        <textarea name="exception_reason"
                                                                  minlength="10"
                                                                  maxlength="2000"
                                                                  rows="2"
                                                                  class="jca-input"></textarea>
                                                    </label>
                                                    <label class="jca-check text-sm">
                                                        <input type="checkbox"
                                                               name="identity_confirmed"
                                                               value="1"
                                                               required>
                                                        I checked that this login belongs
                                                        to {{ $learner->full_name }}.
                                                    </label>
                                                    <x-ui.button type="submit" variant="secondary">
                                                        Connect login
                                                    </x-ui.button>
                                                </form>
                                            @endif
                                            @if ($canRemove)
                                                <form method="POST"
                                                      action="{{ route('people.admin.students.destroy', $learner) }}"
                                                      class="space-y-2">
                                                    @csrf
                                                    @method('DELETE')
                                                    <p class="text-sm font-bold text-brand-navy-900">
                                                        Remove this mistaken, unused learner record
                                                    </p>
                                                    <label class="jca-label">
                                                        Reason
                                                        <textarea name="reason"
                                                                  required
                                                                  minlength="10"
                                                                  maxlength="2000"
                                                                  rows="2"
                                                                  class="jca-input"></textarea>
                                                    </label>
                                                    <label class="jca-check text-sm">
                                                        <input type="checkbox"
                                                               name="deletion_confirmed"
                                                               value="1"
                                                               required>
                                                        I checked that this learner
                                                        has no enrollment, login,
                                                        or relationship history.
                                                    </label>
                                                    <x-ui.button type="submit" variant="danger">
                                                        Delete record
                                                    </x-ui.button>
                                                </form>
                                            @endif
                                        </div>
                                    </details>
                                @endif
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="jca-card p-6">
                    <x-ui.empty-state
                        icon="search"
                        :title="$search !== '' ? 'No learner matches “'.$search.'”' : 'No learners yet'"
                        description="Learners appear here after the Registrar issues walk-in enrollment access."
                    />
                </div>
            @endforelse
            @if ($learners->hasPages())
                <div class="jca-card p-4">
                    {{ $learners->links() }}
                </div>
            @endif
        </section>
        {{-- Relationships --}}
        <section class="jca-card overflow-hidden" aria-labelledby="links-title">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border px-5 py-4 sm:px-6">
                <h2 id="links-title" class="text-lg font-black text-brand-navy-900">
                    Relationships
                </h2>
                <p class="text-sm font-semibold text-muted">
                    {{ $links->total() }}
                    {{ Str::plural('relationship', $links->total()) }}
                    · unverified first
                </p>
            </div>
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="bg-canvas text-xs font-extrabold uppercase tracking-wider text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 sm:px-6">
                                Parent/guardian
                            </th>
                            <th scope="col" class="px-3 py-2.5">
                                Learner
                            </th>
                            <th scope="col" class="px-3 py-2.5">
                                Relationship
                            </th>
                            <th scope="col" class="px-3 py-2.5">
                                Account
                            </th>
                            <th scope="col" class="px-5 py-2.5 sm:px-6">
                                Verification
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($links as $link)
                            <tr>
                                <th scope="row" class="px-5 py-2.5 font-bold text-brand-navy-900 sm:px-6">
                                    {{ $link->guardian->full_name }}
@if ($isJuniorHigh && filled($link->guardian->parent_login_id))
    <span class="mt-1 block font-mono text-xs font-bold text-brand-navy-700">
        Parent Login ID: {{ $link->guardian->parent_login_id }}
    </span>
@endif
                                </th>
                                <td class="px-3 py-2.5">
                                    {{ $link->student->full_name }}
                                    <span class="block font-mono text-xs text-muted">
                                        {{ $link->student->student_number }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    {{ $relationships[$link->relationship_type] ?? Str::headline($link->relationship_type) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($link->guardian->user_id)
                                        <span class="text-success-700 font-bold">
                                            Has Account
                                        </span>
                                    @else
                                        <span class="text-muted">
                                            No Account Yet
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 sm:px-6">
                                    @if ($link->verified_at)
                                        <span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-extrabold text-success-700">
                                            Verified
                                        </span>
                                    @else
                                        <form method="POST"
                                              action="{{ route('people.admin.links.verify', ['guardian' => $link->guardian, 'student' => $link->student]) }}">
                                            @csrf
                                            <x-ui.button type="submit" variant="secondary">
                                                Verify
                                            </x-ui.button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-6 text-center text-muted sm:px-6">
                                    No relationships yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($links->hasPages())
                <div class="border-t border-border p-4">
                    {{ $links->links() }}
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
