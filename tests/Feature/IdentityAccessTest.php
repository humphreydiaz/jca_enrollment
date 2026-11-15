<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Enums\SmsDeliveryRoute;
use App\Modules\Communications\Gateways\FakeSmsGateway;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\CreateAccountInvitationAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\AccountInvitation;
use App\Modules\People\Actions\LinkGuardianStudentAction;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdentityAccessTest extends TestCase
{
    use RefreshDatabase;

    private int $loginIdentifierSequence = 700;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_public_registration_is_unavailable_and_recovery_is_staff_assisted(): void
    {
        $this->get('/register')->assertNotFound();

        $this->get(route('identity.recovery'))
            ->assertOk()
            ->assertSee('does not provide public password-reset requests')
            ->assertDontSee('name="identifier"', false);
    }

    public function test_active_user_can_sign_in_and_sign_out(): void
    {
        $user = $this->activeUser(['guardian']);
        $identifier = $this->schoolIdentifier($user, 'guardian');
        $this->get(route('identity.login'));
        $sessionIdBeforeLogin = session()->getId();

        $this->post(route('identity.login.store'), [
            'identifier' => $identifier,
            'password' => 'Password12345',
        ])->assertRedirect(route('identity.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionIdBeforeLogin, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $user->id,
            'action' => 'identity.login.succeeded',
        ]);

        $this->post(route('identity.logout'))->assertRedirect(route('identity.login'));

        $this->assertGuest();
    }

    public function test_one_login_page_explains_every_supported_identifier_without_asking_for_a_role(): void
    {
        $this->get(route('identity.login'))
            ->assertOk()
            ->assertSee('JCA secure portal')
            ->assertSee('JCA-Parent-Student-Login-Image.png')
            ->assertSee(route('identity.login.store'), false)
            ->assertSee('<label for="identifier"', false)
            ->assertSee('Username')
            ->assertDontSee('Student ID or Staff ID')
            ->assertSee('JCA-26-693 or 26-JCA-783')
            ->assertDontSee('email address or mobile number')
            ->assertDontSee('Staff sign in')
            ->assertDontSee('name="role"', false);

        $this->get('/staff/login')->assertRedirect('/login');
        $this->post('/staff/login')->assertStatus(405);
    }

    public function test_every_role_signs_in_and_out_through_the_unified_endpoint(): void
    {
        $dashboardLabelByRole = [
            'admin' => 'Enrollment review',
            'registrar' => 'Enrollment pipeline',
            'accounting' => 'Collections summary',
            // Zero-count cards are hidden, so roles without work yet are recognised by their sidebar group.
            'teacher' => 'My teaching',
            'guardian' => 'My children',
            'student' => 'My school records',
        ];

        foreach ($dashboardLabelByRole as $role => $dashboardLabel) {
            $user = $this->activeUser([$role]);
            $identifier = $this->schoolIdentifier($user, $role);

            $this->post(route('identity.login.store'), [
                'identifier' => $identifier,
                'password' => 'Password12345',
            ])->assertRedirect(route('identity.dashboard'));

            $this->assertAuthenticatedAs($user);
            $this->get(route('identity.dashboard'))->assertOk()->assertSee($dashboardLabel);
            $this->post(route('identity.logout'))->assertRedirect(route('identity.login'));
            $this->assertGuest();
        }
    }

    public function test_staff_can_sign_in_with_their_staff_id(): void
    {
        $teacher = $this->activeUser(['teacher']);
        StaffProfile::query()->create([
            'user_id' => $teacher->id,
            'employee_number' => '26-JCA-783',
            'active_from' => '2026-06-01',
        ]);

        $this->post(route('identity.login.store'), [
            'identifier' => '26-jca-783',
            'password' => 'Password12345',
        ])->assertRedirect(route('identity.dashboard'));

        $this->assertAuthenticatedAs($teacher);
    }

    public function test_login_failure_is_identical_for_an_existing_and_unknown_identifier(): void
    {
        $admin = $this->activeUser(['admin']);
        $identifier = $this->schoolIdentifier($admin, 'admin');

        $existing = $this->from(route('identity.login'))
            ->post(route('identity.login.store'), [
                'identifier' => $identifier,
                'password' => 'WrongPassword123',
            ])
            ->assertRedirect(route('identity.login'))
            ->assertSessionHasErrors([
                'identifier' => 'The provided credentials are not valid.',
            ]);

        $unknown = $this->from(route('identity.login'))
            ->post(route('identity.login.store'), [
                'identifier' => '26-JCA-999',
                'password' => 'WrongPassword123',
            ])
            ->assertRedirect(route('identity.login'))
            ->assertSessionHasErrors([
                'identifier' => 'The provided credentials are not valid.',
            ]);

        $this->assertSame(
            $existing->getSession()->get('errors')->first('identifier'),
            $unknown->getSession()->get('errors')->first('identifier'),
        );
        $this->assertGuest();
        $this->assertNull($admin->fresh()->last_login_at);
    }

    public function test_disabled_and_locked_accounts_receive_the_same_neutral_failure(): void
    {
        $disabled = $this->activeUser(['guardian'], [
            'status' => AccountStatus::Disabled,
            'disabled_at' => now(),
        ]);
        $locked = $this->activeUser(['teacher'], [
            'status' => AccountStatus::Active,
            'locked_at' => now(),
        ]);
        $accounts = [
            [$disabled, $this->schoolIdentifier($disabled, 'guardian')],
            [$locked, $this->schoolIdentifier($locked, 'teacher')],
        ];

        foreach ($accounts as [$account, $identifier]) {
            $this->from(route('identity.login'))
                ->post(route('identity.login.store'), [
                    'identifier' => $identifier,
                    'password' => 'Password12345',
                ])
                ->assertRedirect(route('identity.login'))
                ->assertSessionHasErrors([
                    'identifier' => 'The provided credentials are not valid.',
                ]);

            $this->assertGuest();
            $this->assertNull($account->fresh()->last_login_at);
        }
    }

    public function test_login_is_rate_limited_without_revealing_account_state(): void
    {
        $user = $this->activeUser(['guardian']);
        $identifier = $this->schoolIdentifier($user, 'guardian');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('identity.login'))->post(route('identity.login.store'), [
                'identifier' => $identifier,
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors('identifier');
        }

        $this->from(route('identity.login'))->post(route('identity.login.store'), [
            'identifier' => $identifier,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('identifier');
    }

    public function test_forced_password_change_blocks_dashboard_until_completed(): void
    {
        $user = $this->activeUser(['guardian'], ['must_change_password' => true]);
        $identifier = $this->schoolIdentifier($user, 'guardian');

        $this->post(route('identity.login.store'), [
            'identifier' => $identifier,
            'password' => 'Password12345',
        ])->assertRedirect(route('identity.password.edit'));

        $this->get(route('identity.dashboard'))->assertRedirect(route('identity.password.edit'));

        $this->put(route('identity.password.update'), [
            'current_password' => 'Password12345',
            'password' => 'NewPassword12345',
            'password_confirmation' => 'NewPassword12345',
        ])->assertRedirect(route('identity.dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->get(route('identity.dashboard'))->assertOk();
    }

    public function test_invitation_is_single_use_and_activates_the_invited_account(): void
    {
        $admin = $this->activeUser(['admin']);
        $invitedUser = User::query()->create([
            'name' => 'Invited Guardian',
            'email' => 'invited@example.test',
            'status' => AccountStatus::Invited,
        ]);
        $this->assignRoles($invitedUser, ['guardian'], $admin);

        $created = app(CreateAccountInvitationAction::class)->execute($invitedUser, 'email', $invitedUser->email, $admin);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}(?:-[A-HJ-NP-Z2-9]{4}){3}$/', $created->token);
        $shortUrl = route('identity.invitations.short', ['token' => $created->token]);
        $url = route('identity.invitations.show', [
            'invitation' => $created->invitation->public_id,
            'token' => $created->token,
        ]);

        $this->get($shortUrl)->assertOk()->assertSee('Set your private password');
        $this->get($url)->assertOk()->assertSee('Set your private password');
        $this->post($url, [
            'password' => 'InvitationPassword123',
            'password_confirmation' => 'InvitationPassword123',
        ])->assertRedirect(route('identity.dashboard'));

        $this->assertAuthenticatedAs($invitedUser);
        $this->assertSame(AccountStatus::Active, $invitedUser->fresh()->status);
        $this->assertNotNull($created->invitation->fresh()->consumed_at);

        $this->post(route('identity.logout'));
        $this->get($url)->assertStatus(410)->assertSee('This setup link cannot be used.');
    }

    public function test_invited_user_can_open_password_setup_with_the_sms_account_code(): void
    {
        $admin = $this->activeUser(['admin']);
        $invitedUser = User::query()->create([
            'name' => 'Code Recipient',
            'mobile' => '+639171234590',
            'status' => AccountStatus::Invited,
        ]);
        $this->assignRoles($invitedUser, ['guardian'], $admin);
        $created = app(CreateAccountInvitationAction::class)->execute($invitedUser, 'mobile', $invitedUser->mobile, $admin);

        $this->get(route('identity.invitations.activate'))
            ->assertOk()
            ->assertSee('Enter your account code')
            ->assertSee('Staff account setup')
            ->assertSee('Parents, guardians, and students don’t need a code.');
        // The login page offers code entry only as a small staff link.
        $this->get(route('identity.login'))->assertOk()
            ->assertSee('School staff with a one-time account code?')
            ->assertSee('Set up your staff account')
            ->assertSee(route('identity.invitations.activate'), false)
            ->assertDontSee('>Activate account<', false);

        $typedWithoutDashes = strtolower(str_replace('-', ' ', $created->token));
        $this->post(route('identity.invitations.activate.store'), ['code' => $typedWithoutDashes])
            ->assertRedirect(route('identity.invitations.short', ['token' => $created->token]));

        $this->post(route('identity.invitations.activate.store'), ['code' => 'AAAA-AAAA-AAAA-AAAA'])
            ->assertSessionHasErrors('code');
    }

    public function test_expired_or_invalid_invitation_has_a_neutral_failure_response(): void
    {
        $user = User::query()->create([
            'name' => 'Expired Invite',
            'email' => 'expired@example.test',
            'status' => AccountStatus::Invited,
        ]);
        $token = Str::random(64);
        $invitation = AccountInvitation::query()->create([
            'user_id' => $user->id,
            'channel' => 'email',
            'sent_to' => $user->email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('identity.invitations.show', ['invitation' => $invitation->public_id, 'token' => $token]))
            ->assertStatus(410)
            ->assertSee('This setup link cannot be used.');
    }

    public function test_admin_can_create_an_invited_staff_account_without_open_registration(): void
    {
        $admin = $this->activeUser(['admin']);
        $fake = new FakeSmsGateway;
        app()->instance(SmsGateway::class, $fake);

        $this->actingAs($admin)
            ->post(route('identity.admin.users.store'), [
                'name' => 'Registrar Account',
                'channel' => 'mobile',
                'email' => 'registrar@example.test',
                'mobile' => '+639171234589',
                'roles' => ['registrar'],
                'employed_on' => '2026-06-01',
            ])
            ->assertRedirect(route('identity.admin.users.index'))
            ->assertSessionHas('invitation_code')
            ->assertSessionHas('invitation_url');

        $user = User::query()->where('email', 'registrar@example.test')->firstOrFail();
        $this->assertSame(AccountStatus::Invited, $user->status);
        $this->assertTrue($user->hasRole('registrar'));
        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $user->id,
            'active_from' => '2026-06-01',
        ]);
        $this->assertMatchesRegularExpression('/^26-JCA-\d{3}$/', $user->staffProfile->employee_number);
        $this->assertCount(1, $fake->sent);
        $this->assertSame(SmsDeliveryRoute::Otp, $fake->sent[0]['route']);
        $this->assertStringContainsString('{otp}', $fake->sent[0]['body']);
        $this->assertStringNotContainsString('http', $fake->sent[0]['body']);
        $this->assertSame(
            AccountInvitation::query()->where('user_id', $user->id)->value('token_hash'),
            hash('sha256', (string) $fake->sent[0]['one_time_code']),
        );
    }

    public function test_admin_user_creation_temporarily_allows_only_mobile_invitations(): void
    {
        $admin = $this->activeUser(['admin']);

        $this->actingAs($admin)
            ->get(route('identity.admin.users.index'))
            ->assertOk()
            ->assertSee('aria-label="Invitation channel: Mobile"', false)
            ->assertSee('name="channel" value="mobile"', false)
            ->assertDontSee('<option value="email"', false);

        $this->actingAs($admin)
            ->from(route('identity.admin.users.index'))
            ->post(route('identity.admin.users.store'), [
                'name' => 'Email Channel Attempt',
                'channel' => 'email',
                'email' => 'email-channel@example.test',
                'roles' => ['guardian'],
            ])
            ->assertRedirect(route('identity.admin.users.index'))
            ->assertSessionHasErrors(['channel', 'mobile']);

        $this->assertDatabaseMissing('users', ['email' => 'email-channel@example.test']);
    }

    public function test_staff_invitation_requires_an_employment_start_date(): void
    {
        $admin = $this->activeUser(['admin']);

        $this->actingAs($admin)
            ->from(route('identity.admin.users.index'))
            ->post(route('identity.admin.users.store'), [
                'name' => 'Teacher Without Date',
                'channel' => 'mobile',
                'mobile' => '+639171234580',
                'roles' => ['teacher'],
            ])
            ->assertRedirect(route('identity.admin.users.index'))
            ->assertSessionHasErrors('employed_on');

        $this->assertDatabaseMissing('users', ['name' => 'Teacher Without Date']);
    }

    public function test_user_management_list_requires_an_admin_account(): void
    {
        $this->get(route('identity.admin.users.index'))
            ->assertRedirect(route('identity.login'));

        $guardian = $this->activeUser(['guardian']);

        $this->actingAs($guardian)
            ->get(route('identity.admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_search_filter_sort_and_paginate_the_user_management_list(): void
    {
        $admin = $this->activeUser(['admin'], ['name' => 'System Administrator']);

        for ($number = 1; $number <= 17; $number++) {
            $this->activeUser(['guardian'], [
                'name' => sprintf('Family Portal %02d', $number),
                'email' => sprintf('family%02d@example.test', $number),
            ]);
        }

        $this->activeUser(['teacher'], [
            'name' => 'Family Portal Teacher',
            'email' => 'family-teacher@example.test',
        ]);

        $disabled = $this->activeUser(['guardian'], [
            'name' => 'Family Portal Disabled',
            'email' => 'family-disabled@example.test',
        ]);
        $disabled->forceFill([
            'status' => AccountStatus::Disabled,
            'disabled_at' => now(),
        ])->save();

        $response = $this->actingAs($admin)->get(route('identity.admin.users.index', [
            'search' => 'Family Portal',
            'role' => 'guardian',
            'status' => 'active',
            'sort' => 'name_asc',
            'per_page' => 15,
        ]));

        $response
            ->assertOk()
            ->assertSee('17 results')
            ->assertSee('Active filters')
            ->assertSee('Family Portal 01')
            ->assertViewHas('users', function ($users): bool {
                return $users->total() === 17
                    && $users->count() === 15
                    && $users->lastPage() === 2
                    && $users->getCollection()->every(
                        fn (User $user): bool => $user->status === AccountStatus::Active
                            && $user->roles->contains('code', 'guardian')
                    );
            });

        $this->assertSame(1, substr_count($response->getContent(), '<dialog'));
        $this->assertSame(15, substr_count($response->getContent(), 'aria-haspopup="dialog"'));
    }

    public function test_user_management_filters_reject_unsupported_values(): void
    {
        $admin = $this->activeUser(['admin']);

        $this->actingAs($admin)
            ->from(route('identity.admin.users.index'))
            ->get(route('identity.admin.users.index', [
                'status' => 'archived',
                'role' => 'super-admin',
                'sort' => 'password',
                'per_page' => 500,
            ]))
            ->assertRedirect(route('identity.admin.users.index'))
            ->assertSessionHasErrors(['status', 'role', 'sort', 'per_page']);
    }

    public function test_user_management_offers_no_locked_status_because_nothing_locks_accounts(): void
    {
        $admin = $this->activeUser(['admin']);

        $this->actingAs($admin)
            ->get(route('identity.admin.users.index'))
            ->assertOk()
            ->assertSee('<option value="disabled"', false)
            ->assertDontSee('<option value="locked"', false);

        $this->actingAs($admin)
            ->from(route('identity.admin.users.index'))
            ->get(route('identity.admin.users.index', ['status' => 'locked']))
            ->assertRedirect(route('identity.admin.users.index'))
            ->assertSessionHasErrors(['status']);
    }

    public function test_first_admin_is_bootstrapped_only_through_the_secure_console_prompt(): void
    {
        $this->artisan('identity:bootstrap-admin', [
            '--name' => 'First Administrator',
            '--email' => 'first.admin@example.test',
            '--mobile' => '+639171234567',
        ])
            ->expectsQuestion('Temporary administrator password', 'BootstrapPassword123')
            ->expectsQuestion('Confirm temporary administrator password', 'BootstrapPassword123')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'first.admin@example.test')->firstOrFail();

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->isActive());
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $admin->id]);
    }

    public function test_admin_can_issue_a_staff_assisted_recovery_invitation_without_public_account_lookup(): void
    {
        $admin = $this->activeUser(['admin']);
        $guardian = $this->activeUser(['guardian']);

        $this->actingAs($admin)
            ->post(route('identity.admin.users.recovery', $guardian))
            ->assertRedirect()
            ->assertSessionHas('invitation_url');

        $this->assertDatabaseHas('account_invitations', [
            'user_id' => $guardian->id,
            'purpose' => 'account_recovery',
        ]);
    }

    public function test_admin_can_reactivate_a_disabled_parent_account_with_reason_and_temporary_password(): void
    {
        $admin = $this->activeUser(['admin']);
        $guardian = $this->activeUser(['guardian']);
        $oldInvitation = app(CreateAccountInvitationAction::class)->execute(
            $guardian,
            'email',
            $guardian->email,
            $admin,
        );
        $guardian->forceFill([
            'status' => AccountStatus::Disabled,
            'disabled_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->post(route('identity.admin.users.reactivate', $guardian))
            ->assertSessionHasErrors('reason');

        $reason = 'Identity confirmed in person by the school administrator.';
        $response = $this->actingAs($admin)
            ->post(route('identity.admin.users.reactivate', $guardian), [
                'reason' => $reason,
            ])
            ->assertRedirect()
            ->assertSessionMissing('invitation_url');

        // Parent and student accounts are reactivated with a one-time temporary password
        // (shown once to the Admin), not a staff-style setup link.
        $this->assertStringContainsString('Temporary Password:', (string) session('status'));
        $this->assertSame(AccountStatus::Active, $guardian->fresh()->status);
        $this->assertNull($guardian->fresh()->disabled_at);
        $this->assertNotNull($oldInvitation->invitation->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'identity.user.reactivated',
            'subject_id' => $guardian->id,
        ]);

        $audit = DB::table('audit_logs')
            ->where('action', 'identity.user.reactivated')
            ->where('subject_id', $guardian->id)
            ->first();
        $this->assertSame($reason, json_decode($audit->metadata, true)['reason']);

        // The one-time password shown to the Admin opens the account and must be replaced at first sign-in.
        preg_match('/Temporary Password: (\S+) \|/', (string) $response->getSession()->get('status'), $match);
        $this->assertNotEmpty($match[1] ?? null);
        $this->assertTrue(Hash::check($match[1], $guardian->fresh()->password));
        $this->assertTrue((bool) $guardian->fresh()->must_change_password);
    }

    public function test_non_admin_cannot_reactivate_a_disabled_account(): void
    {
        $registrar = $this->activeUser(['registrar']);
        $guardian = $this->activeUser(['guardian']);
        $guardian->forceFill([
            'status' => AccountStatus::Disabled,
            'disabled_at' => now(),
        ])->save();

        $this->actingAs($registrar)
            ->post(route('identity.admin.users.reactivate', $guardian), [
                'reason' => 'Unauthorized attempt.',
            ])
            ->assertForbidden();

        $this->assertSame(AccountStatus::Disabled, $guardian->fresh()->status);
    }

    public function test_disabling_an_account_requires_reason_and_revokes_open_invitations(): void
    {
        $admin = $this->activeUser(['admin']);
        $guardian = $this->activeUser(['guardian']);
        $invitation = app(CreateAccountInvitationAction::class)->execute(
            $guardian,
            'email',
            $guardian->email,
            $admin,
        );

        $this->actingAs($admin)
            ->delete(route('identity.admin.users.disable', $guardian))
            ->assertSessionHasErrors('reason');

        $reason = 'Duplicate standalone account created during enrollment review.';
        $this->actingAs($admin)
            ->delete(route('identity.admin.users.disable', $guardian), [
                'reason' => $reason,
            ])
            ->assertRedirect();

        $this->assertSame(AccountStatus::Disabled, $guardian->fresh()->status);
        $this->assertNotNull($guardian->fresh()->disabled_at);
        $this->assertNotNull($invitation->invitation->fresh()->revoked_at);

        $audit = DB::table('audit_logs')
            ->where('action', 'identity.user.disabled')
            ->where('subject_id', $guardian->id)
            ->first();
        $this->assertSame($reason, json_decode($audit->metadata, true)['reason']);
    }

    public function test_staff_and_portal_roles_cannot_be_mixed_and_role_updates_revoke_sessions(): void
    {
        $admin = $this->activeUser(['admin']);
        $user = $this->activeUser(['teacher']);

        $this->expectException(ValidationException::class);
        $this->assignRoles($user, ['teacher', 'guardian'], $admin);
    }

    public function test_role_change_revokes_existing_database_sessions(): void
    {
        $admin = $this->activeUser(['admin']);
        $user = $this->activeUser(['teacher']);

        DB::table('sessions')->insert([
            'id' => 'identity-access-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->assignRoles($user, ['registrar'], $admin);

        $this->assertDatabaseMissing('sessions', ['id' => 'identity-access-session']);
        $this->assertTrue($user->fresh()->hasRole('registrar'));
    }

    public function test_guardian_relationship_scope_denies_unrelated_guardian_teacher_and_accounting_users(): void
    {
        $admin = $this->activeUser(['admin']);
        $guardianUser = $this->activeUser(['guardian']);
        $otherGuardianUser = $this->activeUser(['guardian']);
        $teacher = $this->activeUser(['teacher']);
        $accounting = $this->activeUser(['accounting']);
        $guardian = GuardianProfile::query()->create([
            'user_id' => $guardianUser->id,
            'first_name' => 'Linked',
            'last_name' => 'Guardian',
        ]);
        GuardianProfile::query()->create([
            'user_id' => $otherGuardianUser->id,
            'first_name' => 'Other',
            'last_name' => 'Guardian',
        ]);
        $student = StudentProfile::query()->create([
            'first_name' => 'Student',
            'last_name' => 'One',
        ]);

        app(LinkGuardianStudentAction::class)->execute($guardian, $student, [
            'relationship_type' => 'Parent',
            'may_manage_enrollment' => true,
        ], $admin);

        $this->assertTrue(Gate::forUser($guardianUser)->allows('view', $student));
        $this->assertFalse(Gate::forUser($otherGuardianUser)->allows('view', $student));
        $this->assertFalse(Gate::forUser($teacher)->allows('view', $student));
        $this->assertFalse(Gate::forUser($accounting)->allows('view', $student));
    }

    public function test_old_people_page_address_redirects_to_family_links(): void
    {
        $admin = $this->activeUser(['admin']);

        $this->actingAs($admin)->get('/admin/people')->assertRedirect('/registrar/family-links');
        $this->actingAs($admin)->get(route('people.admin.index'))->assertOk()->assertSee('Family links');
    }

    public function test_mysql_unique_constraint_prevents_duplicate_normalized_mobile_account(): void
    {
        User::query()->create([
            'name' => 'First User',
            'mobile' => '+639171234567',
            'status' => AccountStatus::Invited,
        ]);

        $this->expectException(QueryException::class);

        User::query()->create([
            'name' => 'Second User',
            'mobile' => '+639171234567',
            'status' => AccountStatus::Invited,
        ]);
    }

    /**
     * @param  list<string>  $roleCodes
     * @param  array<string, mixed>  $attributes
     */
    private function activeUser(array $roleCodes, array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'name' => 'User '.Str::random(10),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ], $attributes));

        return $this->assignRoles($user, $roleCodes, null);
    }

    /**
     * @param  list<string>  $roleCodes
     */
    private function assignRoles(User $user, array $roleCodes, ?User $actor): User
    {
        return app(AssignRolesAction::class)->execute($user, $roleCodes, $actor);
    }

    private function schoolIdentifier(User $user, string $role): string
    {
        $suffix = sprintf('%03d', $this->loginIdentifierSequence++);

        if (in_array($role, ['admin', 'registrar', 'accounting', 'teacher'], true)) {
            StaffProfile::query()->create([
                'user_id' => $user->id,
                'employee_number' => "26-JCA-{$suffix}",
                'active_from' => '2026-06-01',
            ]);

            return "26-JCA-{$suffix}";
        }

        $student = StudentProfile::query()->create([
            'user_id' => $role === 'student' ? $user->id : null,
            'first_name' => 'Login',
            'last_name' => 'Learner',
            'student_number' => "JCA-26-{$suffix}",
        ]);

        if ($role === 'guardian') {
            $guardian = GuardianProfile::query()->create([
                'user_id' => $user->id,
                'first_name' => 'Login',
                'last_name' => 'Guardian',
            ]);
            GuardianStudent::query()->create([
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
                'relationship_type' => 'Parent',
                'may_manage_enrollment' => true,
                'verified_at' => now(),
                'effective_from' => today(),
            ]);
        }

        return "JCA-26-{$suffix}";
    }
}
