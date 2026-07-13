<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductionReadinessCheck extends Command
{
    protected $signature = 'jca:production-check {--skip-runtime : Check configuration without database or storage probes}';

    protected $description = 'Fail closed when required production security, infrastructure, or approval gates are missing';

    public function handle(): int
    {
        $checks = $this->configurationChecks();

        if (! $this->option('skip-runtime')) {
            $checks = array_merge($checks, $this->runtimeChecks());
        }

        $failed = false;
        foreach ($checks as $name => $passed) {
            $this->line(($passed ? '[PASS] ' : '[FAIL] ').$name);
            $failed = $failed || ! $passed;
        }

        $this->newLine();
        $this->line($failed ? 'Production readiness checks failed closed.' : 'Production readiness checks passed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, bool> */
    private function configurationChecks(): array
    {
        $key = (string) config('app.key');
        $database = config('database.connections.'.config('database.default'), []);
        $logStack = config('logging.channels.stack.channels', []);
        $smsEnabled = (bool) config('communications.sms.enabled');
        $smsConfigured = ! $smsEnabled || (
            config('communications.sms.driver') === 'semaphore'
            && filled(config('communications.sms.semaphore.api_key'))
            && filled(config('communications.sms.semaphore.sender_name'))
            && config('security.approval_gates.sms') === true
        );

        return [
            'production environment selected' => app()->environment('production'),
            'debug mode disabled' => config('app.debug') === false,
            'application URL uses HTTPS' => str_starts_with((string) config('app.url'), 'https://'),
            'application key is generated' => strlen($key) >= 32 && ! str_contains(strtolower($key), 'generate'),
            'MySQL is the primary database' => config('database.default') === 'mysql',
            'database account is least privilege' => filled($database['username'] ?? null) && strtolower((string) ($database['username'] ?? '')) !== 'root' && filled($database['password'] ?? null),
            'database queue is enabled after commit' => config('queue.default') === 'database' && config('queue.connections.database.after_commit') === true,
            'failed jobs use the database UUID store' => config('queue.failed.driver') === 'database-uuids',
            'database cache is enabled' => config('cache.default') === 'database',
            'database sessions are encrypted' => config('session.driver') === 'database' && config('session.encrypt') === true,
            'session cookie is secure and HTTP-only' => config('session.secure') === true && config('session.http_only') === true,
            'session SameSite policy is restrictive' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'daily redacted logging is enabled above debug' => in_array('daily', is_array($logStack) ? $logStack : [], true) && config('logging.channels.daily.level') !== 'debug',
            'documents disk is private' => $this->privateDiskIsSafe('documents'),
            'reports disk is private' => $this->privateDiskIsSafe('reports'),
            'live SMS is disabled or explicitly approved and configured' => $smsConfigured,
            'outbound mail is configured' => ! in_array(config('mail.default'), ['array', 'log'], true),
            'backup encryption is configured' => config('operations.backup.encrypted') === true,
            'offsite backup is configured' => config('operations.backup.offsite') === true,
            'backup failure monitoring is configured' => config('operations.backup.monitoring') === true,
            'retention policy approval is recorded' => config('security.approval_gates.retention') === true,
            'finance policy approval is recorded' => config('security.approval_gates.finance') === true,
            'academic catalog approval is recorded' => config('security.approval_gates.academics') === true,
            'support workflow approval is recorded' => config('security.approval_gates.support') === true,
            'official report approval is recorded' => config('security.approval_gates.reports') === true,
            'hosting and recovery approval is recorded' => config('security.approval_gates.hosting_recovery') === true,
            'role-owner access and UAT approval is recorded' => config('security.approval_gates.access_uat') === true,
        ];
    }

    /** @return array<string, bool> */
    private function runtimeChecks(): array
    {
        $checks = [];
        $checks['database connection succeeds'] = $this->probe(fn (): bool => (int) DB::select('SELECT 1 AS ready')[0]->ready === 1);
        $checks['required operations tables exist'] = $this->probe(fn (): bool => collect(['jobs', 'failed_jobs', 'sessions', 'cache', 'export_jobs'])->every(fn (string $table): bool => Schema::hasTable($table)));
        $checks['database cache read/write succeeds'] = $this->probe(function (): bool {
            $key = 'production-check:'.Str::ulid();
            Cache::store('database')->put($key, 'ready', now()->addMinute());
            $passed = Cache::store('database')->get($key) === 'ready';
            Cache::store('database')->forget($key);

            return $passed;
        });
        $checks['documents disk read/write/delete succeeds'] = $this->probe(fn (): bool => $this->probeDisk('documents'));
        $checks['reports disk read/write/delete succeeds'] = $this->probe(fn (): bool => $this->probeDisk('reports'));
        $checks['queue backlog is below alert threshold'] = $this->probe(fn (): bool => DB::table('jobs')->count() <= (int) config('operations.queue.max_pending_jobs'));
        $checks['failed-job count is below alert threshold'] = $this->probe(fn (): bool => DB::table('failed_jobs')->count() <= (int) config('operations.queue.max_failed_jobs'));
        $checks['development fixtures are absent'] = $this->probe(fn (): bool => $this->developmentFixturesAreAbsent());

        return $checks;
    }

    private function privateDiskIsSafe(string $disk): bool
    {
        $configuration = config('filesystems.disks.'.$disk, []);
        if (($configuration['visibility'] ?? null) !== 'private' || ($configuration['serve'] ?? false) === true) {
            return false;
        }

        if (($configuration['driver'] ?? null) !== 'local') {
            return ! isset($configuration['url']);
        }

        $root = str_replace('\\', '/', strtolower((string) ($configuration['root'] ?? '')));
        $public = rtrim(str_replace('\\', '/', strtolower(public_path())), '/').'/';

        return $root !== '' && ! str_starts_with(rtrim($root, '/').'/', $public);
    }

    private function probeDisk(string $disk): bool
    {
        $path = '.readiness/'.Str::ulid().'.txt';
        if (! Storage::disk($disk)->put($path, 'readiness')) {
            return false;
        }

        $passed = Storage::disk($disk)->get($path) === 'readiness';
        Storage::disk($disk)->delete($path);

        return $passed && ! Storage::disk($disk)->exists($path);
    }

    private function developmentFixturesAreAbsent(): bool
    {
        $developmentYear = Schema::hasTable('academic_years') && DB::table('academic_years')->where(fn ($query) => $query->where('code', 'like', 'DEV-%')->orWhere('name', 'like', 'DEVELOPMENT ONLY%'))->exists();
        $developmentCampaign = Schema::hasTable('discount_campaigns') && DB::table('discount_campaigns')->where('is_development_fixture', true)->exists();
        $developmentSchedule = Schema::hasTable('fee_schedules') && DB::table('fee_schedules')->where('name', 'like', 'DEVELOPMENT ONLY%')->exists();
        $developmentExam = Schema::hasTable('exam_periods') && DB::table('exam_periods')->where('name', 'like', 'DEVELOPMENT ONLY%')->exists();

        return ! ($developmentYear || $developmentCampaign || $developmentSchedule || $developmentExam);
    }

    private function probe(callable $probe): bool
    {
        try {
            return $probe() === true;
        } catch (Throwable) {
            return false;
        }
    }
}
