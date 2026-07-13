<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Reporting\Models\ExportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseNineHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_responses_have_safe_headers_and_request_correlation(): void
    {
        $response = $this->withHeader('X-Request-ID', "unsafe\r\nheader")->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');

        $requestId = (string) $response->headers->get('X-Request-ID');
        $this->assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $requestId);

        $this->withHeader('X-Request-ID', 'phase9-valid-request')->get('/')
            ->assertHeader('X-Request-ID', 'phase9-valid-request');
    }

    public function test_hsts_is_emitted_only_for_secure_production_requests(): void
    {
        $this->app['env'] = 'production';
        config()->set('security.hsts.enabled', true);
        config()->set('security.hsts.max_age', 3600);
        config()->set('security.hsts.include_subdomains', true);

        $this->get('https://enrollment.example.test/')
            ->assertHeader('Strict-Transport-Security', 'max-age=3600; includeSubDomains');
    }

    public function test_readiness_check_fails_closed_without_production_approvals(): void
    {
        $this->artisan('jca:production-check', ['--skip-runtime' => true])
            ->expectsOutputToContain('[FAIL] production environment selected')
            ->expectsOutputToContain('Production readiness checks failed closed.')
            ->assertFailed();
    }

    public function test_readiness_check_passes_with_secure_configuration_and_runtime_probes(): void
    {
        $this->setProductionReadyConfiguration();

        $this->artisan('jca:production-check')
            ->expectsOutputToContain('[PASS] database connection succeeds')
            ->expectsOutputToContain('[PASS] development fixtures are absent')
            ->expectsOutputToContain('Production readiness checks passed.')
            ->assertSuccessful();
    }

    public function test_expired_report_files_are_pruned_without_erasing_history(): void
    {
        Storage::fake('reports');
        $user = User::factory()->create(['name' => 'Fictional Phase Nine Admin']);
        Storage::disk('reports')->put('expired/fictional.csv', 'fictional');
        $export = ExportJob::query()->create([
            'requested_by' => $user->id,
            'report_code' => 'students',
            'field_snapshot' => ['student_number'],
            'status' => 'completed',
            'disk' => 'reports',
            'storage_path' => 'expired/fictional.csv',
            'download_name' => 'fictional.csv',
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('reports:prune-exports')->expectsOutput('Expired report exports pruned: 1')->assertSuccessful();

        Storage::disk('reports')->assertMissing('expired/fictional.csv');
        $export->refresh();
        $this->assertSame('expired', $export->status);
        $this->assertNull($export->storage_path);
        $this->assertNotNull($export->deleted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reporting.export.expired', 'subject_id' => $export->id]);
    }

    public function test_scheduler_and_production_example_use_safe_defaults(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('reports:prune-exports')
            ->expectsOutputToContain('queue:prune-failed')
            ->assertSuccessful();

        $example = (string) file_get_contents(base_path('.env.production.example'));
        $this->assertStringContainsString('APP_ENV=production', $example);
        $this->assertStringContainsString('APP_DEBUG=false', $example);
        $this->assertStringContainsString('DB_USERNAME=jca_app', $example);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $example);
        $this->assertStringContainsString('SMS_ENABLED=false', $example);
        $this->assertStringContainsString('JCA_ACCESS_UAT_APPROVED=false', $example);
        $this->assertStringNotContainsString('DB_USERNAME=root', $example);
        $this->assertStringNotContainsString('SEMAPHORE_API_KEY=sk-', $example);
    }

    private function setProductionReadyConfiguration(): void
    {
        $this->app['env'] = 'production';
        config()->set([
            'app.debug' => false,
            'app.url' => 'https://enrollment.example.test',
            'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'database.default' => 'mysql',
            'database.connections.mysql.username' => 'jca_app',
            'database.connections.mysql.password' => 'fictional-test-password',
            'queue.default' => 'database',
            'queue.connections.database.after_commit' => true,
            'queue.failed.driver' => 'database-uuids',
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'logging.channels.stack.channels' => ['daily'],
            'logging.channels.daily.level' => 'notice',
            'mail.default' => 'smtp',
            'communications.sms.enabled' => false,
            'operations.backup.encrypted' => true,
            'operations.backup.offsite' => true,
            'operations.backup.monitoring' => true,
            'operations.queue.max_pending_jobs' => 100,
            'operations.queue.max_failed_jobs' => 0,
        ]);

        foreach (array_keys((array) config('security.approval_gates')) as $gate) {
            config()->set('security.approval_gates.'.$gate, true);
        }
    }
}
