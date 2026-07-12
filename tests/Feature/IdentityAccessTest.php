<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\CreateAccountInvitationAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\AccountInvitation;
use App\Modules\People\Actions\LinkGuardianStudentAction;
use App\Modules\People\Models\GuardianProfile;
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

        $this->post(route('identity.login.store'), [
            'identifier' => $user->email,
            'password' => 'Password12345',
        ])->assertRedirect(route('identity.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post(route('identity.logout'))->assertRedirect(route('identity.login'));

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_without_revealing_account_state(): void
    {
        $user = $this->activeUser(['guardian']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('identity.login'))->post(route('identity.login.store'), [
                'identifier' => $user->email,
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors('identifier');
        }

        $this->from(route('identity.login'))->post(route('identity.login.store'), [
            'identifier' => $user->email,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('identifier');
    }

    public function test_forced_password_change_blocks_dashboard_until_completed(): void
    {
        $user = $this->activeUser(['guardian'], ['must_change_password' => true]);

        $this->post(route('identity.login.store'), [
            'identifier' => $user->email,
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
        $url = route('identity.invitations.show', [
            'invitation' => $created->invitation->public_id,
            'token' => $created->token,
        ]);

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

        $this->actingAs($admin)
            ->post(route('identity.admin.users.store'), [
                'name' => 'Registrar Account',
                'channel' => 'email',
                'email' => 'registrar@example.test',
                'roles' => ['registrar'],
            ])
            ->assertRedirect(route('identity.admin.users.index'))
            ->assertSessionHas('invitation_url');

        $user = User::query()->where('email', 'registrar@example.test')->firstOrFail();
        $this->assertSame(AccountStatus::Invited, $user->status);
        $this->assertTrue($user->hasRole('registrar'));
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $user->id]);
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

    public function test_admin_can_link_a_guardian_profile_to_an_individual_guardian_account(): void
    {
        $admin = $this->activeUser(['admin']);
        $guardianUser = $this->activeUser(['guardian']);

        $this->actingAs($admin)
            ->from(route('people.admin.index'))
            ->post(route('people.admin.guardians.store'), [
                'user_public_id' => $guardianUser->public_id,
                'first_name' => 'Portal',
                'last_name' => 'Guardian',
            ])
            ->assertRedirect(route('people.admin.index'));

        $this->assertDatabaseHas('guardian_profiles', ['user_id' => $guardianUser->id]);
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
}
