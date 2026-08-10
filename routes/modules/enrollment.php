<?php

use App\Modules\Enrollment\Http\Controllers\GuardianProgressController;
use App\Modules\Enrollment\Http\Controllers\PublicEnrollmentController;
use App\Modules\Enrollment\Http\Controllers\RegistrarEnrollmentController;
use App\Modules\Requirements\Http\Controllers\RequirementDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/apply', [PublicEnrollmentController::class, 'create'])->name('enrollment.public.start');
});
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/apply', [PublicEnrollmentController::class, 'store'])->name('enrollment.public.store');
});
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/applications/{enrollment}', [PublicEnrollmentController::class, 'status'])->name('enrollment.public.status');
    Route::post('/applications/{enrollment}/verify', [PublicEnrollmentController::class, 'verify'])->name('enrollment.public.verify');
    Route::put('/applications/{enrollment}', [PublicEnrollmentController::class, 'update'])->name('enrollment.public.update');
    Route::post('/applications/{enrollment}/submit', [PublicEnrollmentController::class, 'submit'])->name('enrollment.public.submit');
});
Route::middleware('throttle:3,1')->group(function () {
    Route::post('/applications/{enrollment}/resend-code', [PublicEnrollmentController::class, 'resendVerification'])->name('enrollment.public.resend');
});
Route::middleware(['auth', 'password.changed', 'role:admin,registrar'])->prefix('registrar/enrollments')->group(function () {
    Route::get('/', [RegistrarEnrollmentController::class, 'index'])->name('enrollment.registrar.index');
    Route::post('/bulk-transition', [RegistrarEnrollmentController::class, 'bulkTransition'])->name('enrollment.registrar.bulk-transition');
    Route::post('/{enrollment}/verify-contact', [RegistrarEnrollmentController::class, 'verify'])->name('enrollment.registrar.verify');
    Route::post('/{enrollment}/duplicate-review', [RegistrarEnrollmentController::class, 'reviewDuplicate'])->name('enrollment.registrar.duplicate-review');
    Route::post('/{enrollment}/connect-account', [RegistrarEnrollmentController::class, 'connect'])->name('enrollment.registrar.connect');
    Route::post('/{enrollment}/transition', [RegistrarEnrollmentController::class, 'transition'])->name('enrollment.registrar.transition');
});
Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::post('/requirements/{requirement}/soft-copy', [RequirementDocumentController::class, 'store'])->name('requirements.documents.store');
    Route::get('/documents/{document}/preview', [RequirementDocumentController::class, 'preview'])->middleware('exclude.previous-url')->name('requirements.documents.preview');
    Route::get('/documents/{document}/download', [RequirementDocumentController::class, 'download'])->middleware('exclude.previous-url')->name('requirements.documents.download');
});
Route::put('/registrar/requirements/{requirement}/hard-copy', [RequirementDocumentController::class, 'receipt'])->middleware(['auth', 'password.changed', 'role:admin,registrar'])->name('requirements.hard-copy.update');
Route::post('/registrar/requirements/{requirement}/review', [RequirementDocumentController::class, 'review'])->middleware(['auth', 'password.changed', 'role:admin,registrar'])->name('requirements.review');
Route::post('/registrar/requirements/{requirement}/soft-copy/review', [RequirementDocumentController::class, 'reviewSoftCopy'])->middleware(['auth', 'password.changed', 'role:admin,registrar'])->name('requirements.soft-copy.review');
Route::get('/guardian/enrollment-progress', GuardianProgressController::class)->middleware(['auth', 'password.changed', 'role:guardian'])->name('guardian.enrollment.progress');
