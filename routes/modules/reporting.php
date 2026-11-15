<?php

use App\Modules\Reporting\Http\Controllers\AuditLogController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\Shared\Backup\Http\BackupController;
use Illuminate\Support\Facades\Route;

// Reports: Admin, Registrar, Accounting, Teacher
Route::middleware([
    'auth',
    'password.changed',
    'role:admin,registrar,accounting,teacher',
])->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reporting.index');

    Route::get('/reports/{report}', [ReportController::class, 'show'])
        ->name('reporting.show');

    Route::get('/reports/{report}/print', [ReportController::class, 'print'])
        ->name('reporting.print');

    Route::post('/reports/{report}/exports', [ReportController::class, 'export'])
        ->middleware('throttle:5,1')
        ->name('reporting.exports.store');

    Route::get('/report-exports/{export}/download', [ReportController::class, 'download'])
        ->middleware('throttle:30,1')
        ->name('reporting.exports.download');

    Route::delete('/report-exports/{export}', [ReportController::class, 'destroy'])
        ->name('reporting.exports.destroy');
});

// Audit Trail
// Admin: All audit records
// Registrar: Student/Guardian My Information updates only
// Access restriction is enforced by AuditLogPolicy
// and AuditLogController.
Route::middleware([
    'auth',
    'password.changed',
    'role:admin,registrar',
    'throttle:60,1',
])->prefix('admin/audit')->group(function (): void {
    Route::get('/', [AuditLogController::class, 'index'])
        ->name('reporting.audit.index');

    Route::get('/{auditLog}', [AuditLogController::class, 'show'])
        ->name('reporting.audit.show');
});

// D-060: Backup management — Admin only
Route::middleware([
    'auth',
    'password.changed',
    'role:admin',
])->prefix('admin/backups')->group(function (): void {
    $backupName = 'jca-backup-[0-9]{8}-[0-9]{6}(-[a-z0-9-]{1,40})?\.jcabak';

    Route::get('/', [BackupController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('operations.backups.index');

    Route::post('/', [BackupController::class, 'store'])
        ->middleware('throttle:3,10')
        ->name('operations.backups.store');

    Route::get('/{name}/download', [BackupController::class, 'download'])
        ->middleware('throttle:10,1')
        ->where('name', $backupName)
        ->name('operations.backups.download');

    Route::get('/{name}/restore', [BackupController::class, 'confirm'])
        ->where('name', $backupName)
        ->name('operations.backups.restore.confirm');

    Route::post('/{name}/restore', [BackupController::class, 'restore'])
        ->middleware('throttle:5,10')
        ->where('name', $backupName)
        ->name('operations.backups.restore');
});
