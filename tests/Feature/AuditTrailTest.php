<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\Shared\Audit\AuditValueMask;
use App\Modules\Shared\Audit\Models\AuditLog;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_values_are_masked_enough_to_tell_them_apart(): void
    {
        $this->assertSame('+63917•••4567', AuditValueMask::apply(AuditValueMask::MOBILE, '+639171234567'));
        $this->assertSame('0917•••4567', AuditValueMask::apply(AuditValueMask::MOBILE, '09171234567'));
        $this->assertSame('j•••@gmail.com', AuditValueMask::apply(AuditValueMask::EMAIL, 'juan.cruz@gmail.com'));
        $this->assertNull(AuditValueMask::apply(AuditValueMask::MOBILE, ''));
        $this->assertSame('enrolled', AuditValueMask::apply(AuditValueMask::PLAIN, 'enrolled'));
    }

    public function test_record_changes_names_every_changed_field_and_keeps_values_only_where_allowed(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->user('admin');
        $guardian = GuardianProfile::query()->create(['first_name' => 'Ana', 'last_name' => 'Santos']);
        $audit = app(AuditRecorder::class);

        $this->assertNull($audit->recordChanges($admin, 'people.guardian.details_completed', $guardian,
            ['mobile' => '+639171234567', 'last_name' => 'Santos'], ['mobile' => '+639171234567', 'last_name' => 'Santos'], ['mobile' => AuditValueMask::MOBILE]));
        $this->assertSame(0, AuditLog::query()->where('action', 'people.guardian.details_completed')->count());

        $log = $audit->recordChanges($admin, 'people.guardian.details_completed', $guardian,
            ['mobile' => '+639171234567', 'email' => null, 'last_name' => 'Santos'],
            ['mobile' => '+639189876543', 'email' => 'ana@example.org', 'last_name' => 'Reyes'],
            ['mobile' => AuditValueMask::MOBILE, 'email' => AuditValueMask::EMAIL]);

        $this->assertSame(['mobile', 'email', 'last_name'], $log->metadata['fields']);
        $this->assertSame(['old' => '+63917•••4567', 'new' => '+63918•••6543'], $log->metadata['changes']['mobile']);
        $this->assertSame(['old' => null, 'new' => 'a•••@example.org'], $log->metadata['changes']['email']);
        $this->assertArrayNotHasKey('last_name', $log->metadata['changes']);
        $this->assertStringNotContainsString('Reyes', json_encode($log->metadata));
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame($guardian->public_id, $log->subject_public_id);
    }

    public function test_audit_entries_cannot_be_edited_or_deleted(): void
    {
        $log = app(AuditRecorder::class)->record(null, 'fictional.event');

        try {
            $log->update(['action' => 'something.else']);
            $this->fail('An audit entry was edited.');
        } catch (LogicException) {
        }
        try {
            $log->delete();
            $this->fail('An audit entry was deleted.');
        } catch (LogicException) {
        }

        $this->assertSame('fictional.event', $log->fresh()->action);
    }

    public function test_the_admin_audit_entry_shows_what_changed_but_no_other_notes(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->user('admin');
        $guardian = GuardianProfile::query()->create(['first_name' => 'Ana', 'last_name' => 'Santos']);
        $log = app(AuditRecorder::class)->recordChanges($admin, 'people.guardian.details_completed', $guardian,
            ['mobile' => '+639171234567', 'last_name' => 'Santos'], ['mobile' => '+639189876543', 'last_name' => 'Reyes'],
            ['mobile' => AuditValueMask::MOBILE], ['private_reason' => 'DO-NOT-EXPOSE']);

        $this->actingAs($admin)->get(route('reporting.audit.show', $log))->assertOk()
            ->assertSee('Parent/guardian details saved')
            ->assertSee('What changed')
            ->assertSeeInOrder(['Mobile number:', '+63917•••4567', '+63918•••6543'])
            ->assertSeeInOrder(['Last name:', 'changed (value not kept in the audit log)'])
            ->assertDontSee('DO-NOT-EXPOSE')
            ->assertDontSee('Reyes');
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => ucfirst($role).' Person', 'email' => $role.'@example.test', 'password' => 'Password12345', 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, [$role], null);
    }
}
