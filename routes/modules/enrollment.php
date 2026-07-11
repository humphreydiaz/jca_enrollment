<?php

use App\Modules\Enrollment\Http\Controllers\PublicEnrollmentController;
use App\Modules\Enrollment\Http\Controllers\RegistrarEnrollmentController;
use App\Modules\Requirements\Http\Controllers\RequirementDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::get('/apply', [PublicEnrollmentController::class, 'create'])->name('enrollment.public.start');
    Route::post('/apply', [PublicEnrollmentController::class, 'store'])->name('enrollment.public.store');
    Route::get('/applications/{enrollment}', [PublicEnrollmentController::class, 'status'])->name('enrollment.public.status');
});
Route::middleware(['auth', 'password.changed', 'role:admin,registrar'])->prefix('registrar/enrollments')->group(function () {
    Route::get('/', [RegistrarEnrollmentController::class, 'index'])->name('enrollment.registrar.index');
    Route::post('/{enrollment}/verify-contact', [RegistrarEnrollmentController::class, 'verify'])->name('enrollment.registrar.verify');
    Route::post('/{enrollment}/transition', [RegistrarEnrollmentController::class, 'transition'])->name('enrollment.registrar.transition');
});
Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::post('/requirements/{requirement}/documents', [RequirementDocumentController::class, 'store'])->name('requirements.documents.store');
    Route::get('/documents/{document}/download', [RequirementDocumentController::class, 'download'])->name('requirements.documents.download');
});
Route::post('/registrar/requirements/{requirement}/review', [RequirementDocumentController::class, 'review'])->middleware(['auth', 'password.changed', 'role:admin,registrar'])->name('requirements.review');
