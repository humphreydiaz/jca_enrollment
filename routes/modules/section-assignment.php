<?php

use App\Modules\SectionAssignment\Http\Controllers\PortalSectionPrintController;
use App\Modules\SectionAssignment\Http\Controllers\SectionAssignmentController;
use App\Modules\SectionAssignment\Http\Controllers\StudentProgressController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed', 'role:admin,registrar'])->prefix('registrar/section-assignments')->group(function (): void {
    Route::get('/', [SectionAssignmentController::class, 'index'])->name('section-assignment.index');
    Route::post('/enrollments/{enrollment}/confirm', [SectionAssignmentController::class, 'confirm'])->name('section-assignment.confirm');
    Route::post('/{assignment}/transfer', [SectionAssignmentController::class, 'transfer'])->name('section-assignment.transfer');
    Route::post('/enrollments/{enrollment}/promotion', [SectionAssignmentController::class, 'recommendPromotion'])->name('promotion.recommend');
    Route::post('/promotions/{recommendation}/draft', [SectionAssignmentController::class, 'createPromotionDraft'])->name('promotion.create-draft');
});

Route::get('/student/enrollment-progress', StudentProgressController::class)
    ->middleware(['auth', 'password.changed', 'role:student'])
    ->name('student.enrollment.progress');

Route::get('/portal/enrollments/{enrollment}/section-print', PortalSectionPrintController::class)
    ->middleware(['auth', 'password.changed', 'role:guardian,student'])
    ->name('portal.section.print');
