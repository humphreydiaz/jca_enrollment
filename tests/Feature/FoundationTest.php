<?php

namespace Tests\Feature;

use App\Modules\Shared\Jobs\FoundationProbeJob;
use App\Modules\Shared\Logging\RedactsSensitiveLogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_shell_renders_the_jca_foundation_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('JCSGO Christian')
            ->assertSee('Academy R1-Inc.')
            ->assertSee(asset('images/logo/jca_logo.png'), false)
            ->assertSee('Enrollment made clearer for families and school staff.')
            ->assertSee('Skip to main content')
            ->assertSee('jca-shell flex min-h-screen flex-col', false)
            ->assertSee('max-w-7xl flex-1', false)
            ->assertSee('max-w-7xl px-4 py-4 text-sm', false);
    }

    public function test_shared_module_smoke_route_uses_the_authenticated_shell_without_authentication_logic(): void
    {
        $this->get(route('foundation.smoke'))
            ->assertOk()
            ->assertSee('Shared module route loaded')
            ->assertSee('Foundation ready for secure modules');
    }

    public function test_mysql_backed_cache_session_and_queue_infrastructure_are_available(): void
    {
        $this->assertSame('mysql', config('database.default'));

        $cacheKey = 'foundation:'.Str::lower((string) Str::ulid());
        Cache::store('database')->put($cacheKey, 'ready', now()->addMinute());

        $this->assertSame('ready', Cache::store('database')->get($cacheKey));

        $sessionId = 'foundation-'.Str::lower((string) Str::ulid());
        $session = app('session')->driver('database');
        $session->setId($sessionId);
        $session->start();
        $session->put('foundation', 'ready');
        $session->save();

        $this->assertDatabaseCount('sessions', 1);

        Queue::connection('database')->push(new FoundationProbeJob($cacheKey));

        $this->assertDatabaseCount('jobs', 1);

        Cache::store('database')->forget($cacheKey);
    }

    public function test_audit_foundation_uses_public_references_and_lookup_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('audit_logs'));

        foreach (['public_id', 'actor_user_id', 'action', 'subject_type', 'subject_id', 'outcome', 'occurred_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('audit_logs', $column));
        }
    }

    public function test_private_documents_and_safe_logging_are_configured_for_future_modules(): void
    {
        $this->assertSame(storage_path('app/private/documents'), config('filesystems.disks.documents.root'));
        $this->assertSame('private', config('filesystems.disks.documents.visibility'));
        $this->assertContains(
            RedactsSensitiveLogContext::class,
            config('logging.channels.daily.tap'),
        );
    }
}
