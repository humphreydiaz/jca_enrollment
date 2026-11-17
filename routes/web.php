<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::middleware(['auth'])->post(
    '/sidebar-notifications/mark-seen',
    [\App\Http\Controllers\SidebarNotificationController::class, 'markSeen']
)->name('sidebar.notifications.mark-seen');

// Student and Parent/Guardian - My Information
Route::middleware([
    'auth',
    'password.changed',
    'role:student,guardian',
])->group(function () {
    Route::get(
        '/my-information',
        [\App\Http\Controllers\Modules\People\Http\Controllers\MyInformationController::class, 'index']
    )->name('my-information.index');

    Route::patch(
        '/my-information',
        [\App\Http\Controllers\Modules\People\Http\Controllers\MyInformationController::class, 'update']
    )->name('my-information.update');
});

