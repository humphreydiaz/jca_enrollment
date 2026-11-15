<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\IssueWalkInAccessAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\AuthenticateUserAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianStudent;
use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FamilyLinksTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private User $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, GradeLevelSeeder::class]);
        $this->year = AcademicYear::query()->create(['code' => '2026-2027', 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08', 'status' => 'open']);
        $this->registrar = $this->user('registrar');
    }

    public function test_family_links_is_open_to_registrar_and_admin_only(): void
    {
        $this->actingAs($this->registrar)->get(route('people.admin.index'))->assertOk()->assertSee('Family links')->assertDontSee('Repair tools (Admin)');
        $this->actingAs($this->user('admin'))->get('/admin/people')->assertRedirect('/registrar/family-links');
        foreach (['teacher', 'accounting', 'guardian', 'student'] as $role) {
            $this->actingAs($this->user($role))->get(route('people.admin.index'))->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->get(route('people.admin.index'))->assertRedirect(route('identity.login'));
    }

    public function test_junior_high_walk_in_login_is_connected_to_the_learner_record(): void
    {
        $enrollment = $this->walkIn('G7', 'Miguel', 'Santos');

        $this->assertNotNull($enrollment->student_applicant_user_id);
        $this->assertSame($enrollment->student_applicant_user_id, $enrollment->student->fresh()->user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'people.student.account_attached']);
    }

    public function test_existing_junior_high_walk_ins_are_repaired_by_the_migration(): void
    {
        $enrollment = $this->walkIn('G7', 'Miguel', 'Santos');
        $enrollment->student->forceFill(['user_id' => null])->save();

        (require database_path('migrations/2026_10_07_000029_connect_walk_in_student_logins.php'))->up();

        $this->assertSame($enrollment->student_applicant_user_id, $enrollment->student->fresh()->user_id);
    }

    public function test_registrar_grants_an_elementary_learner_a_student_account_beside_the_parent_account(): void
    {
        $enrollment = $this->walkIn('G3', 'Mika', 'Reyes', 'Lorna');
        $student = $enrollment->student;
        $parent = $enrollment->accessOwner;
        $parent->forceFill(['password' => Hash::make('ParentPassword123'), 'must_change_password' => false, 'password_changed_at' => now(), 'temporary_access_expires_at' => null])->save();

        $this->actingAs($this->registrar)->get(route('people.admin.index', ['search' => 'Mika']))->assertOk()
            ->assertSee('Grant student account')->assertSee('Parent-managed grade')->assertSee('Lorna Reyes');

        $this->actingAs($this->registrar)->post(route('people.family.grant-account', $student), ['reason' => 'short', 'grant_for' => $student->public_id])
            ->assertSessionHasErrors(['reason', 'requested_in_person']);

        $response = $this->actingAs($this->registrar)->post(route('people.family.grant-account', $student), [
            'reason' => 'Requested by the mother, Lorna Reyes, at the Registrar office', 'requested_in_person' => '1',
        ])->assertRedirect()->assertSessionHas('granted_access');
        $slip = $response->getSession()->get('granted_access');
        $account = $student->fresh()->user;
        $this->assertNotNull($account);
        $this->assertTrue($account->hasRole('student'));
        $this->assertTrue($account->must_change_password);
        $this->assertSame($student->student_number, $slip['student_number']);
        $this->assertSame($parent->id, $enrollment->guardian->fresh()->user_id, 'The parent keeps their own account.');

        // The slip prints right after granting, then is gone.
        $this->actingAs($this->registrar)->get(route('people.admin.index'))->assertSee('Student account slip')->assertSee($slip['temporary_password']);
        $this->actingAs($this->registrar)->get(route('people.admin.index'))->assertDontSee($slip['temporary_password']);

        // Same Student ID, different passwords: each person reaches their own account.
        $login = app(AuthenticateUserAction::class);
        $this->assertSame($account->id, $login->execute($student->student_number, $slip['temporary_password'])?->id);
        $this->assertSame($parent->id, $login->execute($student->student_number, 'ParentPassword123')?->id);

        // The child may not choose the parent's password (both would be locked out).
        $account->refresh();
        $this->actingAs($account)->put(route('identity.password.update'), [
            'current_password' => $slip['temporary_password'], 'password' => 'ParentPassword123', 'password_confirmation' => 'ParentPassword123',
        ])->assertSessionHasErrors('password');
        $this->actingAs($account)->put(route('identity.password.update'), [
            'current_password' => $slip['temporary_password'], 'password' => 'MikaOwnPassword9', 'password_confirmation' => 'MikaOwnPassword9',
        ])->assertSessionHasNoErrors();

        // While the parent's application is still a draft, the child sees it is being prepared and cannot submit it.
        $account->refresh();
        $this->actingAs($account)->get(route('student.enrollment.progress'))->assertOk()
            ->assertSee('Application being prepared')->assertDontSee('Complete application')->assertDontSee('Section placement is pending');
        $this->actingAs($account)->get(route('enrollment.public.status', $enrollment))->assertOk()->assertDontSee('Submit for Registrar review');
        $this->actingAs($account)->get(route('identity.dashboard'))->assertDontSee('Finish the enrollment application');
        $this->actingAs($parent)->get(route('identity.dashboard'))->assertSee('Finish the enrollment application');

        // A second grant is refused.
        $this->actingAs($this->registrar)->post(route('people.family.grant-account', $student), ['reason' => 'Requested again by the mother in person', 'requested_in_person' => '1'])
            ->assertSessionHasErrors('student');
        $this->assertSame(1, User::query()->whereHas('roles', fn ($q) => $q->where('code', 'student'))->count());
        $this->actingAs($this->user('teacher'))->post(route('people.family.grant-account', $student), ['reason' => 'Requested by the mother in person', 'requested_in_person' => '1'])
            ->assertForbidden();
    }

    public function test_a_parent_with_an_account_is_connected_to_a_sibling(): void
    {
        $older = $this->walkIn('G4', 'Ana', 'Cruz', 'Rosa');
        $younger = $this->walkIn('G1', 'Ben', 'Cruz', 'Rosa');
        $rosa = $older->accessOwner;
        $rosa->forceFill(['password' => Hash::make('RosaPassword123'), 'must_change_password' => false, 'password_changed_at' => now(), 'temporary_access_expires_at' => null])->save();
        $form = 'sibling-'.$younger->student->public_id;

        $this->actingAs($this->registrar)->post(route('people.family.connect-guardian', $younger->student), [
            'sibling_student_number' => 'JCA-99-999', 'relationship_type' => 'mother', 'confirmed_in_person' => '1',
        ])->assertSessionHasErrorsIn($form, 'sibling_student_number');
        $this->actingAs($this->registrar)->post(route('people.family.connect-guardian', $younger->student), [
            'sibling_student_number' => $younger->student->student_number, 'relationship_type' => 'mother', 'confirmed_in_person' => '1',
        ])->assertSessionHasErrorsIn($form, 'sibling_student_number');
        $this->actingAs($this->registrar)->post(route('people.family.connect-guardian', $younger->student), [
            'sibling_student_number' => $older->student->student_number, 'relationship_type' => 'mother',
        ])->assertSessionHasErrorsIn($form, 'confirmed_in_person');

        $this->actingAs($this->registrar)->post(route('people.family.connect-guardian', $younger->student), [
            'sibling_student_number' => $older->student->student_number, 'relationship_type' => 'mother', 'confirmed_in_person' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $link = GuardianStudent::query()->where('guardian_id', $older->guardian->id)->where('student_id', $younger->student->id)->sole();
        $this->assertNotNull($link->verified_at);
        $this->assertTrue((bool) $link->may_manage_enrollment);
        $this->assertSame($rosa->id, app(AuthenticateUserAction::class)->execute($younger->student->student_number, 'RosaPassword123')?->id);

        // Connecting again is refused.
        $this->actingAs($this->registrar)->post(route('people.family.connect-guardian', $younger->student), [
            'sibling_student_number' => $older->student->student_number, 'relationship_type' => 'mother', 'confirmed_in_person' => '1',
        ])->assertSessionHasErrorsIn($form, 'guardian');
    }

    private function walkIn(string $grade, string $first, string $last, string $parentFirst = 'Parent'): Enrollment
    {
        $data = [
            'academic_year_id' => $this->year->id,
            'grade_level_id' => GradeLevel::query()->where('code', $grade)->value('id'),
            'student_category' => 'regular', 'student_first_name' => $first, 'student_last_name' => $last,
            'sex' => 'female', 'birth_date' => '2016-0'.random_int(1, 9).'-1'.random_int(0, 9),
            'guardian_first_name' => $parentFirst, 'guardian_last_name' => $last, 'guardian_relationship' => 'mother',
        ];
        $issued = app(IssueWalkInAccessAction::class)->execute($data, $this->registrar);

        return Enrollment::query()->with(['student', 'guardian', 'accessOwner'])->findOrFail($issued->enrollment->id);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'must_change_password' => false, 'password_changed_at' => now()]);
        app(AssignRolesAction::class)->execute($user, [$role], null);

        return $user->fresh('roles');
    }
}
