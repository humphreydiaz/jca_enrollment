<?php

use App\Modules\Reporting\Http\Controllers\AuditLogController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed', 'role:admin,registrar,accounting,teacher'])->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index'])->name('reporting.index');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reporting.show');
    Route::get('/reports/{report}/print', [ReportController::class, 'print'])->name('reporting.print');
    Route::post('/reports/{report}/exports', [ReportController::class, 'export'])->middleware('throttle:5,1')->name('reporting.exports.store');
    Route::get('/report-exports/{export}/download', [ReportController::class, 'download'])->middleware('throttle:30,1')->name('reporting.exports.download');
    Route::delete('/report-exports/{export}', [ReportController::class, 'destroy'])->name('reporting.exports.destroy');
});

Route::middleware(['auth', 'password.changed', 'role:admin', 'throttle:60,1'])->prefix('admin/audit')->group(function (): void {
    Route::get('/', [AuditLogController::class, 'index'])->name('reporting.audit.index');
    Route::get('/{auditLog}', [AuditLogController::class, 'show'])->name('reporting.audit.show');
});
