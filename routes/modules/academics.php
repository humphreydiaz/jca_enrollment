<?php

use App\Modules\Academics\Http\Controllers\AcademicCatalogController;
use App\Modules\Academics\Http\Controllers\CurriculumController;
use App\Modules\Academics\Http\Controllers\TeacherAcademicController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed'])->prefix('academics')->group(function (): void {
    Route::get('/my-teaching', TeacherAcademicController::class)
        ->middleware('role:teacher')
        ->name('academics.teacher.assignments');

    Route::middleware('role:admin,registrar')->group(function (): void {
        Route::get('/curricula', [CurriculumController::class, 'index'])->name('academics.admin.curricula.index');
        Route::post('/curricula', [CurriculumController::class, 'store'])->name('academics.admin.curricula.store');
        Route::post('/curricula/{curriculum}/subjects', [CurriculumController::class, 'subject'])->name('academics.admin.curricula.subjects.store');
        Route::delete('/curricula/{curriculum}/subjects/{mappingId}', [CurriculumController::class, 'removeSubject'])->name('academics.admin.curricula.subjects.destroy');
        Route::post('/curricula/{curriculum}/sections', [CurriculumController::class, 'section'])->name('academics.admin.curricula.sections.store');
        Route::delete('/curricula/{curriculum}/sections/{template}', [CurriculumController::class, 'removeSection'])->name('academics.admin.curricula.sections.destroy');
        Route::post('/curricula/{curriculum}/sections/{template}/schedules', [CurriculumController::class, 'schedule'])->name('academics.admin.curricula.schedules.store');
        Route::post('/curricula/{curriculum}/publish', [CurriculumController::class, 'publish'])->name('academics.admin.curricula.publish');
        Route::delete('/curricula/{curriculum}', [CurriculumController::class, 'destroy'])->name('academics.admin.curricula.destroy');

        Route::get('/', [AcademicCatalogController::class, 'index'])->name('academics.admin.index');
        Route::get('/setup', [AcademicCatalogController::class, 'setup'])->name('academics.admin.setup');

        Route::post('/academic-years', [AcademicCatalogController::class, 'storeAcademicYear'])->name('academics.admin.academic-years.store');
        Route::patch('/academic-years/{academicYear}', [AcademicCatalogController::class, 'updateAcademicYear'])->name('academics.admin.academic-years.update');
        Route::patch('/academic-years/{academicYear}/enrollment-status', [AcademicCatalogController::class, 'setEnrollmentStatus'])->name('academics.admin.academic-years.enrollment-status');
        Route::delete('/academic-years/{academicYear}', [AcademicCatalogController::class, 'destroyAcademicYear'])->name('academics.admin.academic-years.destroy');

        Route::post('/grade-levels', [AcademicCatalogController::class, 'storeGradeLevel'])->name('academics.admin.grade-levels.store');
        Route::patch('/grade-levels/{gradeLevel}', [AcademicCatalogController::class, 'updateGradeLevel'])->name('academics.admin.grade-levels.update');
        Route::delete('/grade-levels/{gradeLevel}', [AcademicCatalogController::class, 'destroyGradeLevel'])->name('academics.admin.grade-levels.destroy');

        Route::post('/subjects', [AcademicCatalogController::class, 'storeSubject'])->name('academics.admin.subjects.store');
        Route::patch('/subjects/{subject}', [AcademicCatalogController::class, 'updateSubject'])->name('academics.admin.subjects.update');
        Route::delete('/subjects/{subject}', [AcademicCatalogController::class, 'destroySubject'])->name('academics.admin.subjects.destroy');

        Route::post('/sections', [AcademicCatalogController::class, 'storeSection'])->name('academics.admin.sections.store');
        Route::patch('/sections/{section}', [AcademicCatalogController::class, 'updateSection'])->name('academics.admin.sections.update');
        Route::delete('/sections/{section}', [AcademicCatalogController::class, 'destroySection'])->name('academics.admin.sections.destroy');
        Route::post('/sections/{section}/curriculum-subjects', [AcademicCatalogController::class, 'addCurriculumSubjects'])->name('academics.admin.sections.curriculum-subjects.store');

        Route::post('/section-subjects', [AcademicCatalogController::class, 'storeSectionSubject'])->name('academics.admin.section-subjects.store');
        Route::patch('/section-subjects/{sectionSubject}', [AcademicCatalogController::class, 'updateSectionSubject'])->name('academics.admin.section-subjects.update');
        Route::patch('/section-subjects/{sectionSubject}/status', [AcademicCatalogController::class, 'setSectionSubjectStatus'])->name('academics.admin.section-subjects.status');
        Route::delete('/section-subjects/{sectionSubject}', [AcademicCatalogController::class, 'destroySectionSubject'])->name('academics.admin.section-subjects.destroy');

        Route::post('/teaching-assignments', [AcademicCatalogController::class, 'storeTeachingAssignment'])->name('academics.admin.teaching-assignments.store');
        Route::patch('/teaching-assignments/{teachingAssignment}', [AcademicCatalogController::class, 'updateTeachingAssignment'])->name('academics.admin.teaching-assignments.update');
        Route::delete('/teaching-assignments/{teachingAssignment}', [AcademicCatalogController::class, 'destroyTeachingAssignment'])->name('academics.admin.teaching-assignments.destroy');
    });
});
