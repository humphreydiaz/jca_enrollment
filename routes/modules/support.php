<?php

use App\Modules\StudentSupport\Http\Controllers\SupportReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed'])->prefix('support-review')->group(function (): void {
    Route::get('/', [SupportReviewController::class, 'index'])->name('support.queue');
    Route::post('/enrollments/{enrollment}', [SupportReviewController::class, 'create'])->name('support.create');
    Route::get('/{supportCase}', [SupportReviewController::class, 'show'])->name('support.show');
    Route::post('/{supportCase}/assign', [SupportReviewController::class, 'assign'])->name('support.assign');
    Route::post('/{supportCase}/assessments', [SupportReviewController::class, 'assessment'])->name('support.assessment');
    Route::post('/{supportCase}/observations', [SupportReviewController::class, 'observation'])->name('support.observation');
    Route::post('/{supportCase}/summaries', [SupportReviewController::class, 'summary'])->name('support.summary');
});
