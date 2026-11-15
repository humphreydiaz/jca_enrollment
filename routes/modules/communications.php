<?php

use App\Modules\Communications\Http\Controllers\CommunicationsController;
use App\Modules\Communications\Http\Controllers\ContactPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/announcements', [CommunicationsController::class, 'index'])->name('communications.index');
    Route::get('/announcements/{announcement}', [CommunicationsController::class, 'show'])->name('communications.show');
    // D-052: anyone with a number on file may opt in or out of announcement texts.
    Route::middleware('role:guardian,student,teacher,registrar,accounting,admin')->group(function (): void {
        Route::get('/communication-preferences', [ContactPreferenceController::class, 'edit'])->name('communications.preferences.edit');
        Route::put('/communication-preferences', [ContactPreferenceController::class, 'update'])->name('communications.preferences.update');
    });
    Route::redirect('/guardian/communication-preferences', '/communication-preferences');
    Route::middleware('role:admin')->prefix('admin/communications')->group(function (): void {
        Route::get('/', [CommunicationsController::class, 'admin'])->name('communications.admin');
        Route::get('/announcements/{announcement}/preview', [CommunicationsController::class, 'preview'])->name('communications.preview');
        Route::post('/announcements', [CommunicationsController::class, 'store'])->name('communications.store');
        Route::post('/announcements/{announcement}/publish', [CommunicationsController::class, 'publish'])->name('communications.publish');
        Route::get('/announcements/{announcement}/audience', [CommunicationsController::class, 'draftAudiencePreview'])->name('communications.draft-audience');
        // D-052: who a text would reach, the people search, and texting picked people.
        Route::post('/audience', [CommunicationsController::class, 'audiencePreview'])->name('communications.audience');
        Route::get('/people', [CommunicationsController::class, 'people'])->name('communications.people');
        Route::post('/messages', [CommunicationsController::class, 'sendDirect'])->name('communications.direct');
        Route::post('/notifications/{notification}/retry', [CommunicationsController::class, 'retry'])->name('communications.notifications.retry');
        Route::post('/{type}/{publicId}/retry-failed', [CommunicationsController::class, 'retryFailed'])->whereIn('type', ['announcement', 'message'])->name('communications.retry-failed');
    });
});
