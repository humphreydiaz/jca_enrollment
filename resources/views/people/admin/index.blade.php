<x-layouts.app title="People foundation">
    <div class="space-y-8">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="people-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Identity foundation</p>
            <h1 id="people-title" class="mt-2 text-3xl font-black text-brand-navy-900">Guardian and student relationships</h1>
            <p class="mt-3 text-sm leading-6 text-muted">Create profile foundations and verify the relationships that scope portal access. Enrollment details are managed from the enrollment workspace.</p>
            <nav class="mt-5 flex flex-wrap gap-2 border-t border-border pt-5" aria-label="Jump to section">
                <span class="self-center text-xs font-bold uppercase tracking-[0.12em] text-muted">Jump to</span>
                <a href="#student-connections" class="jca-button-secondary text-sm"><x-ui.icon name="users" size="size-4" /> Student &amp; login connections</a>
                <a href="#relationship-verification" class="jca-button-secondary text-sm"><x-ui.icon name="people" size="size-4" /> Relationship verification</a>
            </nav>
        </section>

        <section class="jca-card p-6 sm:p-8" aria-labelledby="enrollment-start-guide-title">
            <h2 id="enrollment-start-guide-title" class="text-xl font-black text-brand-navy-900">Choose the correct enrollment starting point</h2>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-border bg-slate-50 p-5">
                    <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-brand-orange-500">Grades 1–6</p>
                    <h3 class="mt-1 font-black text-brand-navy-900">Guardian-assisted application</h3>
                    <p class="mt-2 text-sm leading-6 text-muted">A parent or guardian starts the enrollment application. The application creates the learner profile; do not create a separate student profile here first.</p>
                </div>
                <div class="rounded-xl border border-border bg-brand-blue-50 p-5">
                    <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-brand-orange-500">Grades 7–12</p>
                    <h3 class="mt-1 font-black text-brand-navy-900">Student self-application</h3>
                    <p class="mt-2 text-sm leading-6 text-muted">Invite the Student login from User management, then let the student sign in and apply. The student is linked to the permanent learner profile only after approval; the guardian account is connected through enrollment review.</p>
                </div>
            </div>
        </section>

        <div class="grid gap-8 xl:grid-cols-2">
            <section class="jca-card p-6" aria-labelledby="guardian-create-title">
                <h2 id="guardian-create-title" class="text-xl font-black text-brand-navy-900">Create guardian profile</h2>
                <form method="POST" action="{{ route('people.admin.guardians.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <x-ui.field for="guardian_user_public_id" label="Linked guardian account (optional)"><select id="guardian_user_public_id" name="user_public_id" class="block w-full rounded-lg border-border px-3 py-3"><option value="">No account yet</option>@foreach($guardianUsers as $user)<option value="{{ $user->public_id }}">{{ $user->name }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field for="guardian_first_name" label="First name"><input id="guardian_first_name" name="first_name" required class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <x-ui.field for="guardian_last_name" label="Last name"><input id="guardian_last_name" name="last_name" required class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <x-ui.field for="guardian_mobile" label="Mobile number"><input id="guardian_mobile" name="mobile" inputmode="tel" class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <x-ui.field for="guardian_email" label="Email address"><input id="guardian_email" name="email" type="email" class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <div class="sm:col-span-2"><x-ui.button type="submit">Save guardian</x-ui.button></div>
                </form>
            </section>

            <section class="jca-card p-6" aria-labelledby="student-create-title">
                <h2 id="student-create-title" class="text-xl font-black text-brand-navy-900">Add an existing student record</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Use this only for a verified existing or legacy school record that did not come through online enrollment. New applicants must use the enrollment application, which creates their learner profile automatically.</p>
                <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-950">Do not use this form before inviting a Grade 7–12 applicant. Create only the Student login in User management.</p>
                <form method="POST" action="{{ route('people.admin.students.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <x-ui.field for="student_first_name" label="First name"><input id="student_first_name" name="first_name" required class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <x-ui.field for="student_last_name" label="Last name"><input id="student_last_name" name="last_name" required class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <x-ui.field for="student_number" label="Student number"><input id="student_number" name="student_number" class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <x-ui.field for="birth_date" label="Birth date"><input id="birth_date" name="birth_date" type="date" class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <div class="sm:col-span-2"><x-ui.button type="submit">Save student</x-ui.button></div>
                </form>
            </section>
        </div>

        <section id="student-connections" class="jca-card scroll-mt-24 overflow-hidden" aria-labelledby="student-account-link-title">
            <div class="border-b border-border p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="student-account-link-title" class="text-xl font-black text-brand-navy-900">Student profile and login connections</h2>
                        <p class="mt-2 max-w-4xl text-sm leading-6 text-muted">A login account does not own a grade or enrollment. It only opens the student profile connected here. Verify the profile name and enrollment before connecting; disconnecting a mistake preserves every enrollment record.</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-brand-blue-100 px-3 py-1 text-xs font-bold text-brand-navy-700">{{ $students->total() }} {{ Str::plural('profile', $students->total()) }}</span>
                </div>

                <form method="GET" action="{{ route('people.admin.index') }}#student-connections" role="search" class="mt-4 flex flex-wrap items-center gap-2">
                    <label for="student_search" class="sr-only">Search student profiles</label>
                    <div class="relative min-w-0 flex-1 sm:max-w-md">
                        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400"><x-ui.icon name="search" size="size-4" /></span>
                        <input id="student_search" type="search" name="student_search" value="{{ $studentSearch }}" maxlength="120" autocomplete="off"
                            placeholder="Search by name, student number, or login"
                            class="jca-input w-full pl-9">
                    </div>
                    <x-ui.button type="submit"><x-ui.icon name="search" size="size-4" /> Search</x-ui.button>
                    @if($studentSearch !== '')
                        <a href="{{ route('people.admin.index') }}#student-connections" class="jca-button-secondary">Clear</a>
                    @endif
                </form>
                @if($studentSearch !== '')
                    <p class="mt-2 text-sm text-muted">{{ $students->total() }} {{ Str::plural('result', $students->total()) }} for &ldquo;{{ $studentSearch }}&rdquo;</p>
                @endif
            </div>
            <div class="divide-y divide-border">
                @forelse ($students as $student)
                    @php($eligibleEnrollment = $student->enrollments->whereIn('status', ['approved', 'enrolled'])->sortByDesc('approved_at')->first())
                    <article class="p-5 sm:p-6">
                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                            <div>
                                <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-muted">Student profile</p>
                                <h3 class="mt-1 text-lg font-black text-brand-navy-900">{{ $student->full_name }}</h3>
                                <p class="mt-1 text-sm text-muted">Student number: {{ $student->student_number ?: 'Not assigned' }}</p>
                                @if($eligibleEnrollment)
                                    <p class="mt-3 rounded-lg bg-brand-blue-100 p-3 text-sm font-semibold text-brand-navy-900">{{ $eligibleEnrollment->gradeLevel?->name }} &middot; {{ $eligibleEnrollment->academicYear?->name }} &middot; {{ str($eligibleEnrollment->status)->headline() }}</p>
                                @else
                                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-950"><strong>No approved enrollment.</strong> Complete enrollment before connecting a Student login.</p>
                                @endif

                                @if($student->status === 'active' && ! $student->user && $student->enrollments->isEmpty() && $student->guardian_links_count === 0)
                                    <details class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                                        <summary class="cursor-pointer list-none font-bold text-brand-red-600">Remove mistaken unused profile</summary>
                                        <form method="POST" action="{{ route('people.admin.students.destroy', $student) }}" class="mt-3 space-y-3">
                                            @csrf @method('DELETE')
                                            <label class="jca-label">Removal reason
                                                <textarea name="reason" required minlength="10" maxlength="2000" class="jca-input" placeholder="Explain how this duplicate or mistaken profile was created"></textarea>
                                            </label>
                                            <label class="jca-check rounded-lg border border-red-200 bg-white p-3">
                                                <input type="checkbox" name="deletion_confirmed" value="1" required>
                                                I verified that this profile has no enrollment, login, or guardian relationship history.
                                            </label>
                                            @if($student->potential_duplicate_enrollments_count > 0)
                                                <p class="text-xs leading-5 text-brand-red-600">This profile is only a pending duplicate candidate for {{ $student->potential_duplicate_enrollments_count }} application(s). Those candidate references will be cleared and audited; the applications will not be deleted.</p>
                                            @else
                                                <p class="text-xs leading-5 text-muted">The removal is audited. Any protected history would block this action on the server.</p>
                                            @endif
                                            <x-ui.button type="submit" variant="danger">Delete mistaken profile</x-ui.button>
                                        </form>
                                    </details>
                                @endif
                            </div>

                            <div>
                                @if($student->user)
                                    <div class="rounded-xl border border-border p-4">
                                        <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-muted">Connected login account</p>
                                        <p class="mt-1 font-black text-brand-navy-900">{{ $student->user->name }}</p>
                                        @if(Str::lower($student->user->name) !== Str::lower($student->full_name))
                                            <p class="mt-2 rounded-lg bg-red-50 p-3 text-sm font-semibold text-brand-red-600">Warning: the login name and student profile name do not match. Student data is blocked in the portal until corrected.</p>
                                        @endif
                                        <details class="mt-3">
                                            <summary class="jca-button-secondary cursor-pointer list-none">Disconnect incorrect account</summary>
                                            <form method="POST" action="{{ route('people.admin.students.account.disconnect', $student) }}" class="mt-3 rounded-lg bg-slate-50 p-3">@csrf @method('DELETE')
                                                <label class="jca-label">Correction reason<textarea name="reason" required minlength="10" maxlength="2000" class="jca-input" placeholder="Explain why this login is being disconnected"></textarea></label>
                                                <p class="mt-2 text-xs text-muted">The login's sessions will be revoked. The profile and enrollment history will stay unchanged.</p>
                                                <x-ui.button type="submit" variant="danger" class="mt-3">Confirm disconnection</x-ui.button>
                                            </form>
                                        </details>
                                    </div>
                                @elseif($eligibleEnrollment)
                                    @php($loginOptions = $studentLoginOptions[$student->id] ?? collect())
                                    <form method="POST" action="{{ route('people.admin.students.account', $student) }}" class="rounded-xl border border-border p-4">
                                        @csrf @method('PUT')
                                        <label class="jca-label">Login account for {{ $student->full_name }}
                                            <select name="user_public_id" required class="jca-input">
                                                <option value="">Select a matching Student login</option>
                                                @foreach ($loginOptions as $user)
                                                    <option value="{{ $user->public_id }}">{{ $user->name }} &middot; {{ $user->email ?? $user->mobile }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="jca-label mt-3">Elementary exception reason
                                            <textarea name="exception_reason" minlength="10" maxlength="2000" class="jca-input" placeholder="Required only for approved Grades 1-6; do not enter N/A"></textarea>
                                        </label>
                                        <label class="jca-check mt-3 rounded-lg border border-border p-3"><input type="checkbox" name="identity_confirmed" value="1" required> I verified that the selected login belongs to {{ $student->full_name }}.</label>
                                        <x-ui.button type="submit" class="mt-3" :disabled="$loginOptions->isEmpty()"><x-ui.icon name="users" /> Connect verified account</x-ui.button>
                                        @if($loginOptions->isEmpty())<p class="mt-2 text-sm text-warning-800">No unconnected Student login matches this profile's name yet.</p>@endif
                                    </form>
                                @else
                                    <div class="rounded-xl border border-dashed border-border p-4 text-sm text-muted">Account connection is unavailable until this profile has an approved or enrolled record.</div>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    @if($studentSearch !== '')
                        <x-ui.empty-state icon="search" title="No matching student profiles" :description="'No student profile matches \''.$studentSearch.'\'. Try a different name, student number, or login.'" />
                    @else
                        <x-ui.empty-state icon="users" title="No student profiles" description="Student profiles created through enrollment intake will appear here." />
                    @endif
                @endforelse
            </div>
            @if ($students->hasPages())
                <div class="border-t border-border p-4 sm:px-6">{{ $students->links() }}</div>
            @endif
        </section>

        <section class="jca-card p-6" aria-labelledby="link-title">
            <h2 id="link-title" class="text-xl font-black text-brand-navy-900">Link guardian and student</h2>
            <form method="POST" action="{{ route('people.admin.links.store') }}" class="mt-5 grid gap-4 md:grid-cols-3">
                @csrf
                <x-ui.field for="guardian_public_id" label="Guardian"><select id="guardian_public_id" name="guardian_public_id" required class="block w-full rounded-lg border-border px-3 py-3"><option value="">Select guardian</option>@foreach($guardians as $guardian)<option value="{{ $guardian->public_id }}">{{ $guardian->full_name }}</option>@endforeach</select></x-ui.field>
                <x-ui.field for="student_public_id" label="Student"><select id="student_public_id" name="student_public_id" required class="block w-full rounded-lg border-border px-3 py-3"><option value="">Select student</option>@foreach($studentOptions as $student)<option value="{{ $student->public_id }}">{{ $student->full_name }}</option>@endforeach</select></x-ui.field>
                <x-ui.field for="relationship_type" label="Relationship"><input id="relationship_type" name="relationship_type" placeholder="Parent, guardian, etc." required class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                <div class="md:col-span-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach (['is_primary_contact' => 'Primary contact', 'may_receive_sms' => 'May receive SMS', 'may_view_financials' => 'May view finance', 'may_manage_enrollment' => 'May manage enrollment', 'has_legal_authority' => 'Legal authority'] as $name => $label)
                        <label class="flex items-center gap-2 text-sm font-semibold text-ink"><input type="checkbox" name="{{ $name }}" value="1" @checked(in_array($name, ['may_receive_sms', 'may_manage_enrollment'], true)) />{{ $label }}</label>
                    @endforeach
                </div>
                <div class="md:col-span-3"><x-ui.button type="submit">Save relationship</x-ui.button></div>
            </form>
        </section>

        <section id="relationship-verification" class="jca-card scroll-mt-24 overflow-hidden" aria-labelledby="links-title">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-6">
                <h2 id="links-title" class="text-xl font-black text-brand-navy-900">Relationship verification</h2>
                <span class="shrink-0 rounded-full bg-brand-blue-100 px-3 py-1 text-xs font-bold text-brand-navy-700">{{ $links->total() }} {{ Str::plural('relationship', $links->total()) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-left text-sm">
                    <thead class="bg-canvas text-muted"><tr><th class="px-6 py-3">Guardian</th><th class="px-6 py-3">Student</th><th class="px-6 py-3">Permissions</th><th class="px-6 py-3">Verification</th></tr></thead>
                    <tbody class="divide-y divide-border bg-white">
                        @forelse ($links as $link)
                            <tr>
                                <td class="px-6 py-4 font-bold text-ink">{{ $link->guardian->full_name }}</td>
                                <td class="px-6 py-4 font-bold text-ink">{{ $link->student->full_name }}</td>
                                <td class="px-6 py-4 text-muted">{{ $link->may_manage_enrollment ? 'Enrollment' : 'View only' }}{{ $link->may_view_financials ? ', finance' : '' }}</td>
                                <td class="px-6 py-4">
                                    @if ($link->verified_at)
                                        <x-ui.status-badge tone="ready">Verified</x-ui.status-badge>
                                    @else
                                        <form method="POST" action="{{ route('people.admin.links.verify', ['guardian' => $link->guardian, 'student' => $link->student]) }}">@csrf<x-ui.button type="submit" variant="secondary">Verify</x-ui.button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-muted">No guardian/student relationships yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($links->hasPages())
                <div class="border-t border-border p-4 sm:px-6">{{ $links->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.app>
