<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Backup\BackupStore;
use App\Modules\Shared\Backup\RestoreBackupAction;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private const DRILL_DATABASE = 'jca_enrollment_testing_drill';

    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        // Backups and documents go to a throwaway folder, never the real storage/app.
        $this->sandbox = storage_path('framework/testing/backup-'.bin2hex(random_bytes(4)));
        mkdir($this->sandbox.'/private/documents', 0777, true);
        config(['operations.backup.path' => $this->sandbox.'/backups', 'filesystems.disks.local.root' => $this->sandbox.'/private']);
        file_put_contents($this->sandbox.'/private/documents/birth-certificate.pdf', '%PDF fictional PSA copy');
        StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Backupson', 'student_number' => 'JCA-26-901']);
        DB::table('sessions')->insert(['id' => 'live-session', 'payload' => 'secret-session', 'last_activity' => time()]);
    }

    protected function tearDown(): void
    {
        BackupStore::removeDirectory($this->sandbox);
        $this->drillPdo()->exec('DROP DATABASE IF EXISTS `'.self::DRILL_DATABASE.'`');
        parent::tearDown();
    }

    public function test_a_backup_is_one_encrypted_file_with_the_database_and_documents(): void
    {
        $this->artisan('jca:backup', ['--label' => 'before hotfix'])->assertSuccessful();

        $backup = app(BackupStore::class)->all()->sole();
        $this->assertMatchesRegularExpression('/^jca-backup-\d{8}-\d{6}-before-hotfix\.jcabak$/', $backup['name']);
        $raw = (string) file_get_contents($this->sandbox.'/backups/'.$backup['name']);
        $this->assertStringStartsWith('JCABAK01A', $raw);
        $this->assertStringNotContainsString('Backupson', $raw);
        $this->assertStringNotContainsString('fictional PSA copy', $raw);
        $this->assertFileExists($this->sandbox.'/backups/.htaccess');

        $manifest = app(RestoreBackupAction::class)->inspect($backup['name']);
        $this->assertSame(1, $manifest['database']['rows']['student_profiles']);
        $this->assertSame(0, $manifest['database']['rows']['sessions']); // runtime state is never backed up
        $this->assertSame(hash('sha256', '%PDF fictional PSA copy'), $manifest['files']['documents/birth-certificate.pdf']['sha256']);
        $this->assertSame($backup['name'], app(BackupStore::class)->status()['last_success_name']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'operations.backup.created']);
    }

    public function test_a_changed_file_or_another_key_is_refused_before_anything_is_restored(): void
    {
        $this->artisan('jca:backup')->assertSuccessful();
        $name = app(BackupStore::class)->all()->sole()['name'];
        $path = $this->sandbox.'/backups/'.$name;

        $original = (string) config('app.key');
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->artisan('jca:restore', ['file' => $name, '--inspect' => true])->assertFailed();
        config(['app.key' => $original]);
        $this->artisan('jca:restore', ['file' => $name, '--inspect' => true])->assertSuccessful();

        $bytes = (string) file_get_contents($path);
        $bytes[200] = $bytes[200] === 'x' ? 'y' : 'x';
        file_put_contents($path, $bytes);
        $this->expectException(RuntimeException::class);
        app(RestoreBackupAction::class)->inspect($name);
    }

    public function test_a_restore_drill_rebuilds_records_and_documents_in_a_separate_database(): void
    {
        $this->artisan('jca:backup')->assertSuccessful();
        $name = app(BackupStore::class)->all()->sole()['name'];

        $this->artisan('jca:restore', ['file' => $name, '--drill-database' => self::DRILL_DATABASE, '--drill-files' => $this->sandbox.'/drill'])
            ->expectsOutputToContain('Row counts match the backup for every table.')
            ->assertSuccessful();

        $drill = $this->drillPdo(self::DRILL_DATABASE);
        $this->assertSame('Backupson', $drill->query("SELECT last_name FROM student_profiles WHERE student_number = 'JCA-26-901'")->fetchColumn());
        $this->assertSame(0, (int) $drill->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
        $this->assertSame((int) DB::table('migrations')->count(), (int) $drill->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
        $this->assertSame('%PDF fictional PSA copy', file_get_contents($this->sandbox.'/drill/documents/birth-certificate.pdf'));
        $this->assertSame(1, StudentProfile::query()->count()); // the live database is untouched

        // A drill never lands on the live database or on a database that already has tables.
        $this->artisan('jca:restore', ['file' => $name, '--drill-database' => DB::connection()->getDatabaseName()])->assertFailed();
        $this->artisan('jca:restore', ['file' => $name, '--drill-database' => self::DRILL_DATABASE, '--drill-files' => $this->sandbox.'/drill2'])->assertFailed();
    }

    public function test_only_an_admin_sees_creates_and_downloads_backups(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->user('admin');
        $registrar = $this->user('registrar');

        $this->get(route('operations.backups.index'))->assertRedirect(route('identity.login'));
        $this->actingAs($registrar)->get(route('operations.backups.index'))->assertForbidden();
        $this->actingAs($registrar)->post(route('operations.backups.store'))->assertForbidden();

        $this->actingAs($admin)->get(route('operations.backups.index'))->assertOk()
            ->assertSee('No backup yet')->assertSee('Reminder: no backup has been made yet')
            ->assertSee('Backups are made only when you click Back up now')->assertDontSee('every night');
        $this->actingAs($admin)->post(route('operations.backups.store'))->assertRedirect(route('operations.backups.index'));
        $name = app(BackupStore::class)->all()->sole()['name'];
        $this->actingAs($admin)->get(route('operations.backups.index'))->assertOk()
            ->assertSee($name)->assertSee('Recent backup on file')->assertDontSee('Reminder:');

        // Backups are made by hand, so after a week without one the page reminds the Admin.
        $this->travel(8)->days();
        $this->actingAs($admin)->get(route('operations.backups.index'))->assertOk()
            ->assertSee('Reminder: the newest backup is more than 7 days old');

        $this->actingAs($registrar)->get(route('operations.backups.download', $name))->assertForbidden();
        $this->actingAs($admin)->get(route('operations.backups.download', $name))->assertOk()->assertDownload($name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'operations.backup.downloaded', 'actor_user_id' => $admin->id]);
        $this->actingAs($admin)->get('/admin/backups/..%2F.env/download')->assertNotFound();
    }

    public function test_one_automatic_backup_runs_daily_at_two_in_the_morning_manila_time(): void
    {
        // D-060 amendment: the owner moved from manual-only backups to one automatic daily backup
        // (the 2026-11-04 Hostinger site runs it from hPanel cron), alongside "Back up now".
        $backups = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'jca:backup'))
            ->values();

        $this->assertCount(1, $backups);
        $this->assertStringContainsString('--label=automatic-daily', (string) $backups[0]->command);
        $this->assertSame('0 2 * * *', $backups[0]->expression);
        $this->assertSame('Asia/Manila', $backups[0]->timezone);
    }

    public function test_old_backups_beyond_the_kept_number_are_removed(): void
    {
        config(['operations.backup.keep' => 2]);
        $store = app(BackupStore::class);
        foreach (['20261001-020000', '20261002-020000', '20261003-020000'] as $stamp) {
            file_put_contents($store->directory()."/jca-backup-{$stamp}.jcabak", 'x');
        }

        $this->assertSame(['jca-backup-20261001-020000.jcabak'], $store->prune());
        $this->assertSame(['jca-backup-20261003-020000.jcabak', 'jca-backup-20261002-020000.jcabak'], $store->all()->pluck('name')->all());
    }

    private function drillPdo(?string $database = null): PDO
    {
        $config = DB::connection()->getConfig();

        return new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']).($database ? ";dbname={$database}" : ''),
            $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => ucfirst($role).' Person', 'email' => $role.'@example.test', 'password' => 'Password12345', 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, [$role], null);
    }
}
