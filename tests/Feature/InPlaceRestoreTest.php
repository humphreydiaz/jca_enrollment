<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\Models\AuditLog;
use App\Modules\Shared\Backup\BackupStore;
use App\Modules\Shared\Backup\SqlImporter;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An in-place restore replaces every table (MySQL DDL commits on its own), so this class rebuilds the clean test
 * schema afterwards for the tests that follow.
 */
class InPlaceRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = storage_path('framework/testing/restore-'.bin2hex(random_bytes(4)));
        mkdir($this->sandbox.'/private/documents', 0777, true);
        config(['operations.backup.path' => $this->sandbox.'/backups', 'filesystems.disks.local.root' => $this->sandbox.'/private']);
        $this->beforeApplicationDestroyed(function (): void {
            if (app()->isDownForMaintenance()) {
                Artisan::call('up');
            }
            BackupStore::removeDirectory($this->sandbox);
            Artisan::call('migrate:fresh');
        });
    }

    public function test_restoring_in_place_brings_back_records_and_documents_after_a_safety_backup(): void
    {
        StudentProfile::query()->create(['first_name' => 'Kept', 'last_name' => 'Learner', 'student_number' => 'JCA-26-902']);
        file_put_contents($this->sandbox.'/private/documents/report-card.pdf', '%PDF original SF9');
        $this->artisan('jca:backup')->assertSuccessful();
        $backup = app(BackupStore::class)->all()->sole()['name'];

        // After the backup: a learner is added by mistake, the original is deleted, and the document is overwritten.
        StudentProfile::query()->create(['first_name' => 'Mistaken', 'last_name' => 'Entry', 'student_number' => 'JCA-26-903']);
        StudentProfile::query()->where('student_number', 'JCA-26-902')->delete();
        file_put_contents($this->sandbox.'/private/documents/report-card.pdf', 'overwritten');

        $this->artisan('jca:restore', ['file' => $backup])
            ->expectsQuestion('Type RESTORE to continue', 'no')
            ->assertFailed();
        $this->assertSame(['JCA-26-903'], StudentProfile::query()->pluck('student_number')->all());

        $this->artisan('jca:restore', ['file' => $backup, '--force' => true])
            ->expectsOutputToContain('Row counts match the backup for every table.')
            ->assertSuccessful();

        DB::purge();
        $this->assertSame(['JCA-26-902'], StudentProfile::query()->pluck('student_number')->all());
        $this->assertSame('%PDF original SF9', file_get_contents($this->sandbox.'/private/documents/report-card.pdf'));
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertDatabaseHas('audit_logs', ['action' => 'operations.backup.restored']);

        // The state just before the restore was saved first, and the replaced documents were kept.
        $this->assertCount(1, app(BackupStore::class)->all()->filter(fn ($file) => str_ends_with($file['name'], '-pre-restore.jcabak')));
        $previous = glob($this->sandbox.'/restore-previous-*')[0] ?? null;
        $this->assertNotNull($previous);
        $this->assertSame('overwritten', file_get_contents($previous.'/documents/report-card.pdf'));
    }

    public function test_the_admin_restores_from_the_backups_page_only_after_confirming_three_ways(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->admin();
        $registrar = $this->user('registrar');
        StudentProfile::query()->create(['first_name' => 'Kept', 'last_name' => 'Learner', 'student_number' => 'JCA-26-902']);
        $this->artisan('jca:backup')->assertSuccessful();
        $backup = app(BackupStore::class)->all()->sole()['name'];
        StudentProfile::query()->create(['first_name' => 'Mistaken', 'last_name' => 'Entry', 'student_number' => 'JCA-26-903']);

        $this->actingAs($admin)->get(route('operations.backups.index'))->assertOk()
            ->assertSee('Are you sure you want to restore?')->assertSee('Type RESTORE to confirm')->assertSee(route('operations.backups.restore.confirm', $backup));
        $this->actingAs($admin)->get(route('operations.backups.restore.confirm', $backup))->assertOk()->assertSee('Restore this backup?');
        $this->actingAs($registrar)->get(route('operations.backups.restore.confirm', $backup))->assertForbidden();
        $this->actingAs($registrar)->post(route('operations.backups.restore', $backup), $this->confirmation())->assertForbidden();

        // Each confirmation is required; a refused attempt changes nothing.
        $from = route('operations.backups.index');
        $this->actingAs($admin)->from($from)->post(route('operations.backups.restore', $backup), [...$this->confirmation(), 'password' => 'wrong-password'])
            ->assertRedirect($from)->assertSessionHasErrors('password');
        $this->actingAs($admin)->from($from)->post(route('operations.backups.restore', $backup), [...$this->confirmation(), 'confirmation' => 'restore'])
            ->assertSessionHasErrors('confirmation');
        $this->actingAs($admin)->from($from)->post(route('operations.backups.restore', $backup), [...$this->confirmation(), 'acknowledge' => null])
            ->assertSessionHasErrors('acknowledge');
        $this->assertSame(2, StudentProfile::query()->count());
        $this->assertSame(1, app(BackupStore::class)->all()->count());

        $this->actingAs($admin)->post(route('operations.backups.restore', $backup), $this->confirmation())
            ->assertRedirect(route('identity.login'))->assertSessionHas('status');
        $this->assertGuest();

        DB::purge();
        $this->assertSame(['JCA-26-902'], StudentProfile::query()->pluck('student_number')->all());
        $this->assertFalse(app()->isDownForMaintenance());
        $restored = AuditLog::query()->where('action', 'operations.backup.restored')->sole();
        $this->assertSame('Admin → Backups', $restored->metadata['via']);
        $this->assertSame($admin->id, $restored->actor_user_id);
        $this->assertCount(1, app(BackupStore::class)->all()->filter(fn ($file) => str_ends_with($file['name'], '-pre-restore.jcabak')));
    }

    public function test_a_restore_that_fails_part_way_puts_the_system_back_automatically(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->admin();
        StudentProfile::query()->create(['first_name' => 'Kept', 'last_name' => 'Learner', 'student_number' => 'JCA-26-902']);
        $this->artisan('jca:backup')->assertSuccessful();
        $backup = app(BackupStore::class)->all()->sole()['name'];
        StudentProfile::query()->create(['first_name' => 'Newer', 'last_name' => 'Entry', 'student_number' => 'JCA-26-904']);
        file_put_contents($this->sandbox.'/private/documents/newer.pdf', '%PDF newer');

        // The first import breaks after every table was dropped; the automatic rollback then imports normally.
        app()->instance(SqlImporter::class, new class extends SqlImporter
        {
            private bool $failed = false;

            public function import(\PDO $pdo, string $path): int
            {
                if (! $this->failed) {
                    $this->failed = true;
                    throw new \RuntimeException('Simulated failure half-way through.');
                }

                return parent::import($pdo, $path);
            }
        });

        $this->actingAs($admin)->from(route('operations.backups.index'))->post(route('operations.backups.restore', $backup), $this->confirmation())
            ->assertRedirect(route('operations.backups.index'))->assertSessionHasErrors('restore');

        DB::purge();
        $this->assertEqualsCanonicalizing(['JCA-26-902', 'JCA-26-904'], StudentProfile::query()->pluck('student_number')->all());
        $this->assertSame('%PDF newer', file_get_contents($this->sandbox.'/private/documents/newer.pdf'));
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertStringContainsString('put back exactly as it was', session('errors')->first('restore'));
    }

    /** @return array<string, string> */
    private function confirmation(): array
    {
        return ['acknowledge' => '1', 'confirmation' => 'RESTORE', 'password' => 'Password12345'];
    }

    private function admin(): User
    {
        return $this->user('admin');
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => ucfirst($role).' Person', 'email' => $role.'@example.test', 'password' => 'Password12345', 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, [$role], null);
    }
}
