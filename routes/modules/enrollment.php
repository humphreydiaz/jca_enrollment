<?php

use App\Modules\Enrollment\Http\Controllers\GuardianProgressController;
use App\Modules\Enrollment\Http\Controllers\PublicEnrollmentController;
use App\Modules\Enrollment\Http\Controllers\RegistrarEnrollmentController;
use App\Modules\Enrollment\Http\Controllers\WalkInAccessController;
use App\Modules\Requirements\Http\Controllers\RequirementDocumentController;
use Illuminate\Support\Facades\Route;

// Parent/Guardian and Student enrollment.
Route::middleware(['auth', 'password.changed', 'role:guardian,student'])
    ->group(function () {
        Route::get('/enrollment/application', [PublicEnrollmentController::class, 'create'])
            ->name('enrollment.public.start');

        Route::get('/applications/{enrollment}', [PublicEnrollmentController::class, 'status'])
            ->name('enrollment.public.status');

        Route::put('/applications/{enrollment}', [PublicEnrollmentController::class, 'update'])
            ->name('enrollment.public.update');

        Route::post('/applications/{enrollment}/submit', [PublicEnrollmentController::class, 'submit'])
            ->name('enrollment.public.submit');
    });

// Registrar and Administrator enrollment management.
Route::middleware(['auth', 'password.changed', 'role:admin,registrar'])
    ->prefix('registrar/enrollments')
    ->group(function () {
        Route::post('/{enrollment}/late-review', [RegistrarEnrollmentController::class, 'reviewLateEnrollment'])
            ->name('enrollment.registrar.late-review');

        Route::get('/', [RegistrarEnrollmentController::class, 'index'])
            ->name('enrollment.registrar.index');

        Route::get('/issue-access', [WalkInAccessController::class, 'create'])
            ->name('enrollment.registrar.issue-access.create');

        Route::post('/issue-access', [WalkInAccessController::class, 'store'])
            ->name('enrollment.registrar.issue-access.store');

        Route::post('/{enrollment}/reissue-access', [WalkInAccessController::class, 'reissue'])
            ->name('enrollment.registrar.issue-access.reissue');

        Route::post('/bulk-transition', [RegistrarEnrollmentController::class, 'bulkTransition'])
            ->name('enrollment.registrar.bulk-transition');

        Route::post('/{enrollment}/duplicate-review', [RegistrarEnrollmentController::class, 'reviewDuplicate'])
            ->name('enrollment.registrar.duplicate-review');

        Route::post('/{enrollment}/transition', [RegistrarEnrollmentController::class, 'transition'])
            ->name('enrollment.registrar.transition');
    });

// Historical documents remain accessible to authorized users.
// New soft-copy uploads and reviews are no longer allowed.
Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/documents/{document}/preview', [RequirementDocumentController::class, 'preview'])
        ->middleware('exclude.previous-url')
        ->name('requirements.documents.preview');

    Route::get('/documents/{document}/download', [RequirementDocumentController::class, 'download'])
        ->middleware('exclude.previous-url')
        ->name('requirements.documents.download');
});

// Physical document receipt and requirements review.
Route::middleware(['auth', 'password.changed', 'role:admin,registrar'])
    ->group(function () {
        Route::put('/registrar/requirements/{requirement}/hard-copy', [RequirementDocumentController::class, 'receipt'])
            ->name('requirements.hard-copy.update');

        Route::post('/registrar/requirements/{requirement}/review', [RequirementDocumentController::class, 'review'])
            ->name('requirements.review');
    });

// Parent/Guardian enrollment progress.
Route::get('/guardian/enrollment-progress', GuardianProgressController::class)
    ->middleware(['auth', 'password.changed', 'role:guardian'])
    ->name('guardian.enrollment.progress');
