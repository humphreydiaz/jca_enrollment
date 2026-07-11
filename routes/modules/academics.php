<?php

use App\Modules\Academics\Http\Controllers\AcademicCatalogController;
use App\Modules\Academics\Http\Controllers\TeacherAcademicController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed'])->prefix('academics')->group(function (): void {
    Route::get('/my-teaching', TeacherAcademicController::class)
        ->middleware('role:teacher')
        ->name('academics.teacher.assignments');

    Route::middleware('role:admin,registrar')->group(function (): void {
        Route::get('/', [AcademicCatalogController::class, 'index'])->name('academics.admin.index');
        Route::post('/academic-years', [AcademicCatalogController::class, 'storeAcademicYear'])->name('academics.admin.academic-years.store');
        Route::patch('/academic-years/{academicYear}', [AcademicCatalogController::class, 'updateAcademicYear'])->name('academics.admin.academic-years.update');
        Route::post('/grade-levels', [AcademicCatalogController::class, 'storeGradeLevel'])->name('academics.admin.grade-levels.store');
        Route::patch('/grade-levels/{gradeLevel}', [AcademicCatalogController::class, 'updateGradeLevel'])->name('academics.admin.grade-levels.update');
        Route::post('/subjects', [AcademicCatalogController::class, 'storeSubject'])->name('academics.admin.subjects.store');
        Route::patch('/subjects/{subject}', [AcademicCatalogController::class, 'updateSubject'])->name('academics.admin.subjects.update');
        Route::post('/sections', [AcademicCatalogController::class, 'storeSection'])->name('academics.admin.sections.store');
        Route::patch('/sections/{section}', [AcademicCatalogController::class, 'updateSection'])->name('academics.admin.sections.update');
        Route::post('/section-subjects', [AcademicCatalogController::class, 'storeSectionSubject'])->name('academics.admin.section-subjects.store');
        Route::patch('/section-subjects/{sectionSubject}', [AcademicCatalogController::class, 'updateSectionSubject'])->name('academics.admin.section-subjects.update');
        Route::post('/teaching-assignments', [AcademicCatalogController::class, 'storeTeachingAssignment'])->name('academics.admin.teaching-assignments.store');
        Route::patch('/teaching-assignments/{teachingAssignment}', [AcademicCatalogController::class, 'updateTeachingAssignment'])->name('academics.admin.teaching-assignments.update');
    });
});
