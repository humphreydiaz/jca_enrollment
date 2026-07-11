<x-layouts.app title="People foundation">
    <div class="space-y-8">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="people-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Identity foundation</p>
            <h1 id="people-title" class="mt-2 text-3xl font-black text-brand-navy-900">Guardian and student relationships</h1>
            <p class="mt-3 text-sm leading-6 text-muted">Create profile foundations and verify the relationships that will later scope portal access. Enrollment data is not collected in this phase.</p>
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
                <h2 id="student-create-title" class="text-xl font-black text-brand-navy-900">Create student profile</h2>
                <form method="POST" action="{{ route('people.admin.students.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <x-ui.field for="student_user_public_id" label="Linked student account (optional)"><select id="student_user_public_id" name="user_public_id" class="block w-full rounded-lg border-border px-3 py-3"><option value="">No account yet</option>@foreach($studentUsers as $user)<option value="{{ $user->public_id }}">{{ $user->name }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field for="student_first_name" label="First name"><input id="student_first_name" name="first_name" required class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <x-ui.field for="student_last_name" label="Last name"><input id="student_last_name" name="last_name" required class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <x-ui.field for="student_number" label="Student number"><input id="student_number" name="student_number" class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <x-ui.field for="birth_date" label="Birth date"><input id="birth_date" name="birth_date" type="date" class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                    <div class="sm:col-span-2"><x-ui.button type="submit">Save student</x-ui.button></div>
                </form>
            </section>
        </div>

        <section class="jca-card p-6" aria-labelledby="link-title">
            <h2 id="link-title" class="text-xl font-black text-brand-navy-900">Link guardian and student</h2>
            <form method="POST" action="{{ route('people.admin.links.store') }}" class="mt-5 grid gap-4 md:grid-cols-3">
                @csrf
                <x-ui.field for="guardian_public_id" label="Guardian"><select id="guardian_public_id" name="guardian_public_id" required class="block w-full rounded-lg border-border px-3 py-3"><option value="">Select guardian</option>@foreach($guardians as $guardian)<option value="{{ $guardian->public_id }}">{{ $guardian->full_name }}</option>@endforeach</select></x-ui.field>
                <x-ui.field for="student_public_id" label="Student"><select id="student_public_id" name="student_public_id" required class="block w-full rounded-lg border-border px-3 py-3"><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->public_id }}">{{ $student->full_name }}</option>@endforeach</select></x-ui.field>
                <x-ui.field for="relationship_type" label="Relationship"><input id="relationship_type" name="relationship_type" placeholder="Parent, guardian, etc." required class="block w-full rounded-lg border-border px-3 py-3" /></x-ui.field>
                <div class="md:col-span-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach (['is_primary_contact' => 'Primary contact', 'may_receive_sms' => 'May receive SMS', 'may_view_financials' => 'May view finance', 'may_manage_enrollment' => 'May manage enrollment', 'has_legal_authority' => 'Legal authority'] as $name => $label)
                        <label class="flex items-center gap-2 text-sm font-semibold text-ink"><input type="checkbox" name="{{ $name }}" value="1" @checked(in_array($name, ['may_receive_sms', 'may_manage_enrollment'], true)) />{{ $label }}</label>
                    @endforeach
                </div>
                <div class="md:col-span-3"><x-ui.button type="submit">Save relationship</x-ui.button></div>
            </form>
        </section>

        <section class="jca-card overflow-hidden" aria-labelledby="links-title">
            <div class="border-b border-border p-6"><h2 id="links-title" class="text-xl font-black text-brand-navy-900">Relationship verification</h2></div>
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
        </section>
    </div>
</x-layouts.app>
