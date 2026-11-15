<?php

use App\Modules\Grading\Http\Controllers\AdminGradeController;
use App\Modules\Grading\Http\Controllers\FamilyGradeController;
use App\Modules\Grading\Http\Controllers\TeacherGradeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::middleware('role:teacher')->prefix('teacher/grades')->group(function (): void {
        Route::get('/', [TeacherGradeController::class, 'index'])->name('grading.teacher.index');
        Route::get('/{assignment}', [TeacherGradeController::class, 'show'])->name('grading.teacher.show');
        Route::post('/{assignment}/enrollments/{enrollment}', [TeacherGradeController::class, 'save'])->name('grading.teacher.save');
        Route::post('/{assignment}/grades/{grade}/submit', [TeacherGradeController::class, 'submit'])->name('grading.teacher.submit');
        Route::post('/{assignment}/grades/{grade}/correction', [TeacherGradeController::class, 'requestCorrection'])->name('grading.teacher.correction');
    });

    Route::middleware('role:admin,registrar')->prefix('admin/grades')->group(function (): void {
        Route::get('/', [AdminGradeController::class, 'index'])->name('grading.admin.index');
        Route::post('/{grade}/finalize', [AdminGradeController::class, 'finalize'])->name('grading.admin.finalize');
        Route::post('/approve-class', [AdminGradeController::class, 'approveClass'])->name('grading.admin.approve-class');
        Route::post('/approve-classes', [AdminGradeController::class, 'approveClasses'])->name('grading.admin.approve-classes');
        Route::post('/review-classes', [AdminGradeController::class, 'reviewClasses'])->name('grading.admin.review-classes');
        Route::get('/classes/{section}/{subject}', [AdminGradeController::class, 'showClass'])->name('grading.admin.class');
        Route::post('/corrections/{correction}/review', [AdminGradeController::class, 'review'])->name('grading.admin.corrections.review');
    });

    Route::get('/portal/grades', FamilyGradeController::class)
        ->middleware('role:guardian,student')->name('grading.family.index');
});
