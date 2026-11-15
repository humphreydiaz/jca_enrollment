<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Communications\Actions\UpdateSmsConsentAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\Requirements\Models\RequirementRule;
use App\Modules\Shared\Audit\Models\AuditLog;
use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WalkInEnrollmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, GradeLevelSeeder::class]);
        $this->year = AcademicYear::query()->create([
            'code' => '2026-2027',
            'name' => 'School Year 2026-2027',
            'starts_on' => '2026-06-08',
            'ends_on' => '2027-04-08',
            'status' => 'open',
            'enrollment_is_open' => true,
        ]);
        $type = DocumentType::query()->create(['code' => 'WALK-IN-TEST', 'name' => 'Walk-in test requirement']);
        RequirementRule::query()->create([
            'academic_year_id' => $this->year->id,
            'student_category' => 'regular',
            'document_type_id' => $type->id,
            'is_required' => true,
            'is_blocking' => true,
        ]);
    }

    public function test_only_admin_or_registrar_can_open_the_walk_in_issuance_screen(): void
    {
        $this->get(route('enrollment.registrar.issue-access.create'))
            ->assertRedirect(route('identity.login'));

        $accounting = $this->userWithRole('accounting');
        $this->actingAs($accounting)
            ->get(route('enrollment.registrar.issue-access.create'))
            ->assertForbidden();

        $registrar = $this->userWithRole('registrar');
        $this->actingAs($registrar)
            ->get(route('enrollment.registrar.issue-access.create'))
            ->assertOk()
            ->assertSee('Issue walk-in enrollment access');
    }

    public function test_validation_failure_creates_no_partial_people_account_or_enrollment_records(): void
    {
        $registrar = $this->userWithRole('registrar');

        $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), [
                'academic_year_id' => $this->year->id,
            ])
            ->assertSessionHasErrors(['grade_level_id', 'student_first_name'])
            ->assertSessionDoesntHaveErrors('guardian_first_name');

        // A guardian-owned grade (Nursery–Grade 6) needs the guardian's name and relationship, but no contact (D-047).
        $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), [
                ...collect($this->issuePayload('G1'))->except(['guardian_first_name', 'guardian_relationship', 'guardian_mobile', 'guardian_email'])->all(),
            ])
            ->assertSessionHasErrors(['guardian_first_name', 'guardian_relationship'])
            ->assertSessionDoesntHaveErrors(['guardian_mobile', 'guardian_email']);

        $this->assertSame(0, StudentProfile::query()->count());
        $this->assertSame(0, GuardianProfile::query()->count());
        $this->assertSame(0, Enrollment::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_registrar_issues_guardian_owned_access_for_elementary_without_sms_verification(): void
    {
        Mail::fake();
        $registrar = $this->userWithRole('registrar');

        $response = $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'))
            ->assertRedirect(route('enrollment.registrar.issue-access.create'))
            ->assertSessionHas('issued_access');

        $credentials = $response->getSession()->get('issued_access');
        $enrollment = Enrollment::query()->with(['student', 'guardian.user', 'accessOwner.roles'])->sole();

        $this->assertMatchesRegularExpression('/^JCA-26-\d{3}$/', $credentials['student_number']);
        $this->assertSame($enrollment->student->student_number, $credentials['student_number']);
        $this->assertTrue($enrollment->accessOwner->hasRole('guardian'));
        $this->assertSame($enrollment->guardian->user_id, $enrollment->accessOwner->id);
        $this->assertTrue(Hash::check($credentials['temporary_password'], $enrollment->accessOwner->password));
        $this->assertTrue($enrollment->accessOwner->must_change_password);
        $this->assertTrue($enrollment->accessOwner->temporary_access_expires_at->isFuture());
        $this->assertNotNull($enrollment->walk_in_verified_at);
        $this->assertNull($enrollment->resume_token_hash);
        $this->assertNull($enrollment->verification_code_hash);
        $this->assertNull($enrollment->contact_verified_at);
        $this->assertDatabaseHas('guardian_student', [
            'guardian_id' => $enrollment->guardian->id,
            'student_id' => $enrollment->student->id,
            'may_manage_enrollment' => true,
            'may_view_financials' => true,
            'may_receive_sms' => false,
        ]);
        $this->assertNotNull(GuardianStudent::query()->sole()->verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'enrollment.walk_in_access.issued']);
        Mail::assertNothingQueued();

        // The issued credentials print as a one-page slip (only right after issuing; nothing else on the page prints).
        $this->actingAs($registrar)->get(route('enrollment.registrar.issue-access.create'))->assertOk()
            ->assertSee('has-print-slip', false)->assertSee('Enrollment access slip')
            ->assertSee($credentials['temporary_password'])->assertSee('Ana Reyes Santos (Parent/Guardian)')
            ->assertSee('body.has-print-slip > :not(.print-slip)', false);
        $this->actingAs($registrar)->get(route('enrollment.registrar.issue-access.create'))->assertOk()
            ->assertDontSee('Enrollment access slip')->assertDontSee($credentials['temporary_password']);
    }

    public function test_registrar_issues_student_owned_access_for_junior_high(): void
    {
        $registrar = $this->userWithRole('registrar');

        $response = $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), $this->juniorHighPayload('G7', 'student.guardian@example.test', '09170000002'))
            ->assertSessionHas('issued_access');

        $credentials = $response->getSession()->get('issued_access');
        $enrollment = Enrollment::query()->with(['accessOwner.roles', 'guardian.user.roles'])->sole();

        // Grade 7-10: the learner owns the application, and the parent gets a separate, linked account.
        $this->assertTrue($enrollment->accessOwner->hasRole('student'));
        $this->assertSame($enrollment->accessOwner->id, $enrollment->student_applicant_user_id);
        $this->assertSame('Student', $credentials['account_owner_type']);
        $this->assertNotNull($enrollment->guardian->user_id);
        $this->assertNotSame($enrollment->accessOwner->id, $enrollment->guardian->user_id);
        $this->assertTrue($enrollment->guardian->user->hasRole('guardian'));
        $this->assertNotNull(GuardianStudent::query()->where('guardian_id', $enrollment->guardian->id)->sole()->verified_at);
    }

    public function test_junior_high_parent_signs_in_with_the_parent_id_printed_on_the_slip(): void
    {
        $registrar = $this->userWithRole('registrar');

        $response = $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), [
                ...$this->issuePayload('G7', 'parent.jhs@example.test', '09170000009'),
                'identity_confirmed' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('issued_both');

        $both = $response->getSession()->get('issued_both');
        $guardian = GuardianProfile::query()->sole();

        $this->assertMatchesRegularExpression('/^PAR-\d{2}-(?!000)\d{3}$/', $both['parent_login']);
        $this->assertSame($both['parent_login'], $guardian->parent_login_id);
        // The opaque public reference is never replaced by the guessable Parent ID.
        $this->assertDoesNotMatchRegularExpression('/^PAR-/', User::query()->findOrFail($guardian->user_id)->public_id);

        auth()->logout();
        $this->post(route('identity.login.store'), [
            'identifier' => strtolower($both['parent_login']),
            'password' => $both['parent_temporary_password'],
        ])->assertRedirect(route('identity.password.edit'));
        $this->assertAuthenticatedAs(User::query()->findOrFail($guardian->user_id));
    }

    public function test_a_second_junior_high_parent_gets_the_next_parent_id(): void
    {
        $registrar = $this->userWithRole('registrar');

        $first = $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), [
            ...$this->issuePayload('G7', 'first.parent@example.test', '09170000011'),
            'identity_confirmed' => '1',
        ])->getSession()->get('issued_both');

        $second = $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), [
            ...$this->issuePayload('G8', 'second.parent@example.test', '09170000012'),
            'student_first_name' => 'Jose',
            'student_last_name' => 'Ramos',
            'birth_date' => '2013-02-01',
            'guardian_last_name' => 'Ramos',
            'identity_confirmed' => '1',
        ])->assertSessionHasNoErrors()->getSession()->get('issued_both');

        $year = now('Asia/Manila')->format('y');
        $this->assertSame("PAR-{$year}-001", $first['parent_login']);
        $this->assertSame("PAR-{$year}-002", $second['parent_login']);
    }

    public function test_possible_duplicate_is_rejected_before_any_access_is_issued(): void
    {
        $registrar = $this->userWithRole('registrar');
        StudentProfile::query()->create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'birth_date' => '2018-04-12',
        ]);

        $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'))
            ->assertSessionHasErrors('student_first_name');

        $this->assertSame(1, StudentProfile::query()->count());
        $this->assertSame(0, GuardianProfile::query()->count());
        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_expired_temporary_password_fails_and_successful_change_clears_expiry(): void
    {
        $registrar = $this->userWithRole('registrar');
        $response = $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'));
        $credentials = $response->getSession()->get('issued_access');
        $owner = Enrollment::query()->with('accessOwner')->sole()->accessOwner;

        $owner->update(['temporary_access_expires_at' => now()->subMinute()]);
        auth()->logout();
        $this->post(route('identity.login.store'), [
            'identifier' => $credentials['student_number'],
            'password' => $credentials['temporary_password'],
        ])->assertSessionHasErrors('identifier');
        $this->assertGuest();

        $owner->update(['temporary_access_expires_at' => now()->addDay()]);
        $this->post(route('identity.login.store'), [
            'identifier' => $credentials['student_number'],
            'password' => $credentials['temporary_password'],
        ])->assertRedirect(route('identity.password.edit'));

        $this->put(route('identity.password.update'), [
            'current_password' => $credentials['temporary_password'],
            'password' => 'PrivateFamilyPass2026',
            'password_confirmation' => 'PrivateFamilyPass2026',
        ])->assertRedirect(route('identity.dashboard'));

        $this->assertFalse($owner->fresh()->must_change_password);
        $this->assertNull($owner->fresh()->temporary_access_expires_at);
    }

    public function test_registrar_can_replace_only_an_unused_temporary_password(): void
    {
        $registrar = $this->userWithRole('registrar');
        $initial = $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'))
            ->getSession()->get('issued_access');
        $enrollment = Enrollment::query()->with('accessOwner')->sole();

        $replacementResponse = $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.reissue', $enrollment))
            ->assertRedirect(route('enrollment.registrar.issue-access.create'))
            ->assertSessionHas('issued_access');
        $replacement = $replacementResponse->getSession()->get('issued_access');
        $owner = $enrollment->accessOwner->fresh();

        $this->assertNotSame($initial['temporary_password'], $replacement['temporary_password']);
        $this->assertFalse(Hash::check($initial['temporary_password'], $owner->password));
        $this->assertTrue(Hash::check($replacement['temporary_password'], $owner->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'enrollment.walk_in_access.reissued']);

        $owner->update(['must_change_password' => false, 'password_changed_at' => now()]);
        $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.reissue', $enrollment))
            ->assertSessionHasErrors('application');
    }

    public function test_temporary_password_is_consumed_by_its_first_successful_sign_in(): void
    {
        $registrar = $this->userWithRole('registrar');
        $issuedResponse = $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'));
        $credentials = $issuedResponse->getSession()->get('issued_access');
        $enrollment = Enrollment::query()->with('accessOwner')->sole();

        auth()->logout();
        $this->post(route('identity.login.store'), [
            'identifier' => $credentials['student_number'],
            'password' => $credentials['temporary_password'],
        ])->assertRedirect(route('identity.password.edit'));
        $this->assertNotNull($enrollment->accessOwner->fresh()->temporary_access_used_at);

        auth()->logout();
        $this->post(route('identity.login.store'), [
            'identifier' => $credentials['student_number'],
            'password' => $credentials['temporary_password'],
        ])->assertSessionHasErrors('identifier');
        $this->assertGuest();

        $replacementResponse = $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.reissue', $enrollment));
        $replacement = $replacementResponse->getSession()->get('issued_access');
        $this->assertNull($enrollment->accessOwner->fresh()->temporary_access_used_at);

        auth()->logout();
        $this->post(route('identity.login.store'), [
            'identifier' => $replacement['student_number'],
            'password' => $replacement['temporary_password'],
        ])->assertRedirect(route('identity.password.edit'));
    }

    public function test_account_owner_can_complete_and_submit_but_an_unrelated_guardian_is_denied(): void
    {
        $registrar = $this->userWithRole('registrar');
        $this->actingAs($registrar)
            ->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'));
        $enrollment = Enrollment::query()->with(['student', 'guardian', 'accessOwner'])->sole();
        $owner = $enrollment->accessOwner;
        $owner->update([
            'must_change_password' => false,
            'password_changed_at' => now(),
            'temporary_access_expires_at' => null,
        ]);

        $otherGuardian = $this->userWithRole('guardian');
        GuardianProfile::query()->create([
            'user_id' => $otherGuardian->id,
            'first_name' => 'Other',
            'last_name' => 'Guardian',
        ]);
        $this->actingAs($otherGuardian)
            ->get(route('enrollment.public.status', $enrollment))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('enrollment.public.start'))
            ->assertOk()
            ->assertSee('Complete the enrollment application')
            ->assertSee('No verification code is sent.')
            ->assertDontSee('Enter the six-digit code')
            ->assertDontSee('Resend code');

        $this->actingAs($owner)
            ->put(route('enrollment.public.update', $enrollment), $this->completionPayload($enrollment))
            ->assertRedirect(route('enrollment.public.status', $enrollment));

        $this->actingAs($owner)
            ->post(route('enrollment.public.submit', $enrollment))
            ->assertRedirect(route('enrollment.public.status', $enrollment));

        $this->assertSame('submitted', $enrollment->fresh()->status);
        $this->assertNotNull($enrollment->fresh()->submitted_at);
    }

    public function test_junior_high_walk_in_needs_parent_details_at_the_counter_and_the_learner_completes_it(): void
    {
        $registrar = $this->userWithRole('registrar');
        $this->actingAs($registrar)->get(route('enrollment.registrar.issue-access.create'))->assertOk()
            ->assertSee('Grade 7–10: Student and Parent/Guardian accounts are issued together')->assertSee('x-show="guardianOwned"', false);

        // Both accounts are issued at the counter, so the parent's details and an identity check are required.
        $withoutParent = collect($this->issuePayload('G7'))->reject(fn ($value, $key) => str_starts_with($key, 'guardian_'))->all();
        $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), $withoutParent)
            ->assertSessionHasErrors(['guardian_first_name', 'guardian_mobile', 'identity_confirmed']);
        $this->assertSame(0, Enrollment::query()->count());

        $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), $this->juniorHighPayload('G7'))->assertSessionHas('issued_access');
        $enrollment = Enrollment::query()->with(['student', 'guardian', 'accessOwner'])->sole();
        $this->assertNull($enrollment->guardian_relationship);
        $this->assertNull($enrollment->accessOwner->email);
        $this->assertNotNull(GuardianStudent::query()->sole()->verified_at);

        $owner = $enrollment->accessOwner;
        $owner->update(['must_change_password' => false, 'password_changed_at' => now(), 'temporary_access_expires_at' => null]);
        // The application starts with empty, editable guardian fields (the placeholder is never shown) and cannot be submitted yet.
        $this->actingAs($owner)->get(route('enrollment.public.start'))->assertOk()
            ->assertDontSee('value="Parent/Guardian"', false)
            ->assertDontSee('recorded by the Registrar in person');
        $this->actingAs($owner)->post(route('enrollment.public.submit', $enrollment));
        $this->assertSame('draft', $enrollment->fresh()->status);

        $complete = [...$this->completionPayload($enrollment), 'guardian_first_name' => 'Lorna', 'guardian_middle_name' => null, 'guardian_last_name' => 'Santos', 'guardian_extension_name' => null, 'guardian_relationship' => 'mother', 'guardian_mobile' => null, 'guardian_email' => null];
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), $complete)
            ->assertSessionHasErrors('guardian_mobile');
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), [...$complete, 'guardian_mobile' => '09170000009'])
            ->assertRedirect(route('enrollment.public.status', $enrollment));

        $enrollment->refresh()->load('guardian');
        $this->assertSame('mother', $enrollment->guardian_relationship);
        $this->assertSame('Lorna Santos', $enrollment->guardian->full_name);
        $this->assertSame('+639170000009', $enrollment->guardian->mobile);
        $this->assertSame('mother', GuardianStudent::query()->sole()->relationship_type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'people.guardian.details_completed']);
        $this->actingAs($owner)->post(route('enrollment.public.submit', $enrollment))->assertRedirect(route('enrollment.public.status', $enrollment));
        $this->assertSame('submitted', $enrollment->fresh()->status);
    }

    public function test_an_unfilled_walk_in_draft_is_shown_as_unfinished_and_cannot_be_submitted_by_the_registrar(): void
    {
        $registrar = $this->userWithRole('registrar');
        $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), $this->juniorHighPayload('G7'));
        $enrollment = Enrollment::query()->with(['student', 'accessOwner'])->sole();
        $student = $enrollment->accessOwner;
        $student->update(['must_change_password' => false, 'password_changed_at' => now(), 'temporary_access_expires_at' => null]);

        // The learner sees an unfinished application, not a pending section placement.
        $this->actingAs($student)->get(route('identity.dashboard'))->assertOk()
            ->assertSee('Finish the enrollment application')->assertSee(route('enrollment.public.status', $enrollment), false);
        $this->actingAs($student)->get(route('student.enrollment.progress'))->assertOk()
            ->assertSee('Your application form is not finished yet')->assertSee('Complete application')
            ->assertDontSee('Section placement is pending');

        // The Registrar is told the form is unfilled and is not offered "Submitted"; forcing it is refused.
        $this->actingAs($registrar)->get(route('enrollment.registrar.index'))->assertOk()
            ->assertSee('Waiting for the application form')->assertSee('parent/guardian details')
            ->assertDontSee('<option value="submitted">', false);
        $this->actingAs($registrar)->post(route('enrollment.registrar.transition', $enrollment), ['status' => 'submitted'])
            ->assertSessionHasErrors('application');
        $this->actingAs($registrar)->post(route('enrollment.registrar.bulk-transition'), ['enrollment_public_ids' => [$enrollment->public_id], 'status' => 'submitted']);
        $this->assertSame('draft', $enrollment->fresh()->status);

        // Once the learner completes and submits it, the page shows the review stage.
        $complete = [...$this->completionPayload($enrollment), 'guardian_first_name' => 'Lorna', 'guardian_middle_name' => null, 'guardian_last_name' => 'Santos', 'guardian_extension_name' => null, 'guardian_relationship' => 'mother', 'guardian_mobile' => '09170000009', 'guardian_email' => null];
        $this->actingAs($student)->put(route('enrollment.public.update', $enrollment), $complete)->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('enrollment.public.submit', $enrollment))->assertSessionHasNoErrors();
        $this->assertSame('submitted', $enrollment->fresh()->status);
        $this->actingAs($student)->get(route('student.enrollment.progress'))->assertOk()
            ->assertSee('Your application is with the Registrar')->assertDontSee('Section placement is pending');
        $this->actingAs($student)->get(route('identity.dashboard'))->assertDontSee('Finish the enrollment application');
    }

    public function test_application_records_an_optional_learner_mobile_and_announcement_sms_opt_ins(): void
    {
        $registrar = $this->userWithRole('registrar');
        $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'));
        $enrollment = Enrollment::query()->with(['student', 'guardian', 'accessOwner'])->sole();
        $owner = $enrollment->accessOwner;
        $owner->update(['must_change_password' => false, 'password_changed_at' => now(), 'temporary_access_expires_at' => null]);

        $this->actingAs($owner)->get(route('enrollment.public.start'))->assertOk()
            ->assertSee("Learner's mobile number")->assertSee('Text school announcements to this number');

        $payload = [...$this->completionPayload($enrollment), 'guardian_mobile' => '09171230000', 'student_mobile' => '0917 123 0001', 'guardian_sms_opt_in' => '1', 'student_sms_opt_in' => '1'];
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), [...$payload, 'student_mobile' => '12345'])->assertSessionHasErrors('student_mobile');
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), [...$payload, 'student_mobile' => '09171230001'])->assertSessionHasNoErrors();

        $this->assertSame('+639171230001', $enrollment->student->fresh()->mobile);
        $this->assertTrue(UpdateSmsConsentAction::granted($enrollment->guardian->fresh()));
        $this->assertTrue(UpdateSmsConsentAction::granted($enrollment->student->fresh()));
        $this->assertDatabaseHas('sms_consents', ['student_id' => $enrollment->student_id, 'source' => 'enrollment_application', 'status' => 'granted']);

        // The learner's number is required; unticking withdraws the opt-in.
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), [...$payload, 'student_mobile' => ''])->assertSessionHasErrors('student_mobile');
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), [...$payload, 'student_mobile' => '09171230001', 'guardian_sms_opt_in' => '0', 'student_sms_opt_in' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse(UpdateSmsConsentAction::granted($enrollment->guardian->fresh()));
        $this->assertFalse(UpdateSmsConsentAction::granted($enrollment->student->fresh()));
    }

    public function test_application_asks_whether_the_learner_is_indigenous_and_records_the_listed_or_typed_group(): void
    {
        $registrar = $this->userWithRole('registrar');
        $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'));
        $enrollment = Enrollment::query()->with(['student', 'guardian', 'accessOwner'])->sole();
        $owner = $enrollment->accessOwner;
        $owner->update(['must_change_password' => false, 'password_changed_at' => now(), 'temporary_access_expires_at' => null]);
        $update = fn (array $changes) => $this->actingAs($owner)->from(route('enrollment.public.start'))
            ->put(route('enrollment.public.update', $enrollment), [...$this->completionPayload($enrollment), ...$changes]);

        // An application never saved leaves the question unanswered; the groups are listed by area.
        $this->actingAs($owner)->get(route('enrollment.public.start'))->assertOk()
            ->assertSee('Indigenous Cultural Community/Indigenous Peoples group? *')
            ->assertDontSee('name="is_indigenous" value="1" required checked', false)
            ->assertDontSee('name="is_indigenous" value="0" required checked', false)
            ->assertSee('<optgroup label="Cordillera and Northern Luzon">', false)
            ->assertSee('<option value="Ibaloi"', false)
            ->assertSee('Other (please specify)');

        // The answer is required; Yes needs a listed group, and "Other" needs its name.
        $update(['is_indigenous' => null])->assertSessionHasErrors('is_indigenous');
        $update(['is_indigenous' => '1'])->assertSessionHasErrors('ip_group');
        $update(['is_indigenous' => '1', 'ip_group' => 'Not a listed group'])->assertSessionHasErrors('ip_group');
        $update(['is_indigenous' => '1', 'ip_group' => 'other', 'ip_group_other' => ''])->assertSessionHasErrors('ip_group_other');
        $this->assertNull($enrollment->student->fresh()->ip_group);

        $update(['is_indigenous' => '1', 'ip_group' => 'Ibaloi'])->assertSessionHasNoErrors();
        $this->assertSame('Ibaloi', $enrollment->student->fresh()->ip_group);
        $this->actingAs($owner)->get(route('enrollment.public.start'))
            ->assertSee('name="is_indigenous" value="1" required checked', false)
            ->assertSee('<option value="Ibaloi" selected>', false);

        $update(['is_indigenous' => '1', 'ip_group' => 'other', 'ip_group_other' => '  Ayta Mag-antsi '])->assertSessionHasNoErrors();
        $this->assertSame('Ayta Mag-antsi', $enrollment->student->fresh()->ip_group);
        $this->actingAs($owner)->get(route('enrollment.public.start'))
            ->assertSee('<option value="other" selected>', false)
            ->assertSee('value="Ayta Mag-antsi"', false);

        // No clears the group even when a stale choice is still posted.
        $update(['is_indigenous' => '0', 'ip_group' => 'Ibaloi'])->assertSessionHasNoErrors();
        $this->assertNull($enrollment->student->fresh()->ip_group);
        $this->actingAs($owner)->get(route('enrollment.public.start'))
            ->assertSee('name="is_indigenous" value="0" required checked', false);
    }

    public function test_contact_and_status_changes_show_who_changed_them_and_when_with_masked_values(): void
    {
        // D-059 defense question: "If a contact number or enrollment status changes, who changed it and when?"
        $registrar = $this->userWithRole('registrar');
        $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'));
        $enrollment = Enrollment::query()->with(['student', 'guardian', 'accessOwner'])->sole();
        $owner = $enrollment->accessOwner;
        $owner->update(['must_change_password' => false, 'password_changed_at' => now(), 'temporary_access_expires_at' => null]);
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), $this->completionPayload($enrollment))->assertSessionHasNoErrors();

        $this->travel(5)->minutes();
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), [...$this->completionPayload($enrollment),
            'guardian_mobile' => '09187654321', 'student_mobile' => '09171230001'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('enrollment.public.submit', $enrollment))->assertRedirect();

        $guardianChange = AuditLog::query()->where('action', 'people.guardian.details_completed')->latest('id')->firstOrFail();
        $this->assertSame($owner->id, $guardianChange->actor_user_id);
        $this->assertSame(['mobile'], $guardianChange->metadata['fields']);
        $this->assertEquals(['old' => '+63917•••0001', 'new' => '+63918•••4321'], $guardianChange->metadata['changes']['mobile']);
        $learnerChange = AuditLog::query()->where('action', 'people.student.application_details_saved')->latest('id')->firstOrFail();
        // The learner mobile is required, so the first save already recorded one (the payload default).
        $this->assertEquals(['old' => '+63918•••0098', 'new' => '+63917•••0001'], $learnerChange->metadata['changes']['mobile']);
        $this->assertStringNotContainsString('7654321', json_encode(AuditLog::query()->pluck('metadata')->all()));

        // The Registrar sees it on the learner's record: masked numbers, the status move, the person, and the time.
        $this->actingAs($registrar)->get(route('people.registrar.students.show', $enrollment->student))->assertOk()
            ->assertSee('Change history')
            ->assertSeeInOrder(['Enrollment status changed', $owner->fresh()->name, 'Status:', 'Draft', 'Submitted'])
            ->assertSeeInOrder(['Parent/guardian details saved', $owner->fresh()->name, 'Mobile number:', '+63917•••0001', '+63918•••4321'])
            ->assertSee('Learner details saved on the application')
            ->assertDontSee('Record viewed');
    }

    public function test_guardian_owned_walk_in_needs_no_contact_and_keeps_the_verified_name_locked(): void
    {
        $registrar = $this->userWithRole('registrar');
        $payload = [...$this->issuePayload('G1'), 'guardian_mobile' => '', 'guardian_email' => ''];
        $credentials = $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), $payload)
            ->assertSessionHas('issued_access')->getSession()->get('issued_access');
        $enrollment = Enrollment::query()->with(['student', 'guardian', 'accessOwner'])->sole();
        $this->assertSame('Ana Reyes Santos', $credentials['account_owner_name']);
        $this->assertSame('mother', $enrollment->guardian_relationship);
        $this->assertNull($enrollment->guardian->mobile);
        $this->assertNull($enrollment->accessOwner->mobile);

        $owner = $enrollment->accessOwner;
        $owner->update(['must_change_password' => false, 'password_changed_at' => now(), 'temporary_access_expires_at' => null]);
        $this->actingAs($owner)->get(route('enrollment.public.start'))->assertOk()->assertSee('recorded by the Registrar in person');
        $base = [...$this->completionPayload($enrollment), 'guardian_mobile' => '09170000008'];
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), [...$base, 'guardian_first_name' => 'Someone'])
            ->assertSessionHasErrors('guardian_first_name');
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), $base)->assertRedirect(route('enrollment.public.status', $enrollment));
        $this->assertSame('+639170000008', $enrollment->guardian->fresh()->mobile);
    }

    public function test_a_contact_already_on_file_is_refused_at_the_counter_but_can_be_left_blank(): void
    {
        $registrar = $this->userWithRole('registrar');
        GuardianProfile::query()->create(['first_name' => 'Existing', 'last_name' => 'Parent', 'mobile' => '+639170000001']);

        $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), $this->issuePayload('G1'))
            ->assertSessionHasErrors('guardian_mobile');
        $this->assertSame(0, Enrollment::query()->count());

        $this->actingAs($registrar)->post(route('enrollment.registrar.issue-access.store'), [...$this->issuePayload('G1'), 'guardian_mobile' => '', 'guardian_email' => ''])
            ->assertSessionHas('issued_access');

        // Entering the same number on the application flags the Registrar's duplicate review instead of merging.
        $enrollment = Enrollment::query()->with(['student', 'guardian', 'accessOwner'])->sole();
        $owner = $enrollment->accessOwner;
        $owner->update(['must_change_password' => false, 'password_changed_at' => now(), 'temporary_access_expires_at' => null]);
        $this->actingAs($owner)->put(route('enrollment.public.update', $enrollment), [...$this->completionPayload($enrollment), 'guardian_mobile' => '09170000001'])
            ->assertRedirect(route('enrollment.public.status', $enrollment));
        $this->assertSame('pending', $enrollment->fresh()->duplicate_review_status);
        $this->assertNotNull($enrollment->fresh()->potential_duplicate_guardian_id);
    }

    public function test_public_start_and_contact_verification_routes_are_retired(): void
    {
        $this->assertFalse(Route::has('enrollment.public.store'));
        $this->assertFalse(Route::has('enrollment.public.verify'));
        $this->assertFalse(Route::has('enrollment.public.resend'));
        $this->get('/apply')->assertNotFound();
        $this->post('/apply')->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
        app(AssignRolesAction::class)->execute($user, [$role], null);

        return $user->fresh('roles');
    }

    /** @return array<string, mixed> */
    /** Grade 7-10: both accounts are issued at the counter after an in-person identity check. */
    private function juniorHighPayload(string $gradeCode, string $email = 'guardian@example.test', string $mobile = '09170000001'): array
    {
        return [...$this->issuePayload($gradeCode, $email, $mobile), 'birth_date' => '2013-04-12', 'identity_confirmed' => '1'];
    }

    private function issuePayload(string $gradeCode, string $email = 'guardian@example.test', string $mobile = '09170000001'): array
    {
        return [
            'academic_year_id' => $this->year->id,
            'grade_level_id' => GradeLevel::query()->where('code', $gradeCode)->value('id'),
            'student_category' => 'regular',
            'student_first_name' => 'Maria',
            'student_middle_name' => 'Cruz',
            'student_last_name' => 'Santos',
            'sex' => 'female',
            'birth_date' => '2018-04-12',
            'guardian_first_name' => 'Ana',
            'guardian_middle_name' => 'Reyes',
            'guardian_last_name' => 'Santos',
            'guardian_relationship' => 'mother',
            'guardian_mobile' => $mobile,
            'guardian_email' => $email,
        ];
    }

    /** @return array<string, mixed> */
    private function completionPayload(Enrollment $enrollment): array
    {
        return [
            'lrn' => '123456789012',
            'student_first_name' => $enrollment->student->first_name,
            'student_middle_name' => $enrollment->student->middle_name,
            'student_last_name' => $enrollment->student->last_name,
            'extension_name' => $enrollment->student->extension_name,
            'sex' => $enrollment->student->sex,
            'birth_date' => $enrollment->student->birth_date->toDateString(),
            'birth_place' => 'Lingayen, Pangasinan',
            'mother_tongue' => 'Filipino',
            'religion' => 'Christianity',
            'is_indigenous' => '0',
            'is_4ps_recipient' => '0',
            'is_learner_with_disability' => '0',
            'current_address' => 'Fictional Street, Lingayen, Pangasinan',
            'same_as_current_address' => '1',
            'father_first_name' => 'Juan',
            'father_last_name' => 'Santos',
            'mother_first_name' => 'Ana',
            'mother_maiden_last_name' => 'Reyes',
            'guardian_first_name' => $enrollment->guardian->first_name,
            'guardian_middle_name' => $enrollment->guardian->middle_name,
            'guardian_last_name' => $enrollment->guardian->last_name,
            'guardian_extension_name' => $enrollment->guardian->extension_name,
            'guardian_relationship' => $enrollment->guardian_relationship,
            'guardian_mobile' => $enrollment->guardian->mobile ?? '09180000099',
            'guardian_email' => $enrollment->guardian->email,
            // The application now requires the learner's mobile number.
            'student_mobile' => $enrollment->student->mobile ?? '09180000098',
            'academic_year_id' => $enrollment->academic_year_id,
            'grade_level_id' => $enrollment->grade_level_id,
            'student_category' => 'regular',
        ];
    }
}
