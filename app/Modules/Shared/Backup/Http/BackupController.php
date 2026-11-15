<?php

namespace App\Modules\Shared\Backup\Http;

use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\Shared\Backup\BackupStore;
use App\Modules\Shared\Backup\CreateBackupAction;
use App\Modules\Shared\Backup\RestoreBackupAction;
use App\Modules\Shared\Backup\RestoreFailedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * D-060: the Admin's view of backups: last-run status, the stored encrypted files, "Back up now", downloading a copy
 * to keep offsite, and restoring one after a confirmation (checkbox, typed RESTORE, and the Admin's password).
 */
class BackupController
{
    public function index(BackupStore $store): View
    {
        Gate::authorize('manage-backups');
        $status = $store->status();
        $lastSuccess = isset($status['last_success_at']) ? Carbon::parse($status['last_success_at']) : null;
        $remindAfterDays = (int) config('operations.backup.remind_after_days');

        return view('operations.backups', [
            'backups' => $store->all(),
            'status' => $status,
            'lastSuccess' => $lastSuccess,
            // Backups are made by hand, so the page reminds the Admin when the newest one is getting old.
            'overdue' => $lastSuccess === null || $lastSuccess->lt(now()->subDays($remindAfterDays)),
            'remindAfterDays' => $remindAfterDays,
            'keep' => (int) config('operations.backup.keep'),
        ]);
    }

    public function store(Request $request, CreateBackupAction $backup): RedirectResponse
    {
        Gate::authorize('manage-backups');
        try {
            $result = $backup->execute($request->user(), 'manual');
        } catch (Throwable $exception) {
            return redirect()->route('operations.backups.index')->withErrors(['backup' => 'The backup failed: '.$exception->getMessage()]);
        }

        return redirect()->route('operations.backups.index')->with('status', "Backup {$result['name']} was created.");
    }

    /** Full-page confirmation, used when the pop-up cannot open (no JavaScript). */
    public function confirm(string $name, BackupStore $store): View
    {
        Gate::authorize('manage-backups');
        $backup = $this->find($store, $name);

        return view('operations.backup-restore', ['backup' => $backup]);
    }

    public function restore(RestoreBackupRequest $request, string $name, BackupStore $store, RestoreBackupAction $restore): RedirectResponse|Response
    {
        $backup = $this->find($store, $name);
        // A restore must not be cut off half-way by a closed tab or the PHP time limit.
        ignore_user_abort(true);
        @set_time_limit(0);

        try {
            $result = $restore->restoreLive($backup['name'], $request->user(), 'Admin → Backups');
        } catch (RestoreFailedException $exception) {
            if ($exception->siteDown) {
                return response()->view('operations.restore-failed', ['message' => $exception->getMessage()], 503);
            }

            return redirect()->route('operations.backups.index')->withErrors(['restore' => $exception->getMessage()]);
        }

        // Every session was replaced, and the restored accounts may differ: everyone signs in again.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $created = isset($result['manifest']['created_at']) ? Carbon::parse($result['manifest']['created_at'])->timezone(config('app.timezone'))->format('d M Y, g:i A') : 'the backup time';

        return redirect()->route('identity.login')->with('status', "The system was restored to {$created}. Please sign in again. The state just before the restore was saved as {$result['safety_backup']}.");
    }

    public function download(Request $request, string $name, BackupStore $store, AuditRecorder $audit): BinaryFileResponse
    {
        Gate::authorize('manage-backups');
        try {
            $path = $store->path($name);
        } catch (RuntimeException) {
            abort(404);
        }
        abort_unless(is_file($path), 404);
        $audit->record($request->user(), 'operations.backup.downloaded', null, ['name' => $name]);

        return response()->download($path, $name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store, private']);
    }

    /** @return array{name: string, size: int, created_at: Carbon} */
    private function find(BackupStore $store, string $name): array
    {
        return $store->all()->firstWhere('name', $name) ?? abort(404);
    }
}
