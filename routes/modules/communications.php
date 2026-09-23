<?php

use App\Modules\Communications\Http\Controllers\CommunicationsController;
use App\Modules\Communications\Http\Controllers\ContactPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/announcements', [CommunicationsController::class, 'index'])->name('communications.index');
    Route::get('/announcements/{announcement}', [CommunicationsController::class, 'show'])->name('communications.show');
    Route::middleware('role:guardian')->prefix('guardian')->group(function (): void {
        Route::get('/communication-preferences', [ContactPreferenceController::class, 'edit'])->name('communications.preferences.edit');
        Route::put('/communication-preferences', [ContactPreferenceController::class, 'update'])->name('communications.preferences.update');
    });
    Route::middleware('role:admin')->prefix('admin/communications')->group(function (): void {
        Route::get('/', [CommunicationsController::class, 'admin'])->name('communications.admin');
        Route::get('/announcements/{announcement}/preview', [CommunicationsController::class, 'preview'])->name('communications.preview');
        Route::post('/announcements', [CommunicationsController::class, 'store'])->name('communications.store');
        Route::post('/announcements/{announcement}/publish', [CommunicationsController::class, 'publish'])->name('communications.publish');
        Route::post('/templates', [CommunicationsController::class, 'storeTemplate'])->name('communications.templates.store');
        Route::patch('/templates/{template}/status', [CommunicationsController::class, 'setTemplateStatus'])->name('communications.templates.status');
        Route::delete('/templates/{template}', [CommunicationsController::class, 'deleteTemplate'])->name('communications.templates.destroy');
        Route::post('/notifications/{notification}/retry', [CommunicationsController::class, 'retry'])->name('communications.notifications.retry');
    });
});
