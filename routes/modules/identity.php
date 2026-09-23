<?php

use App\Modules\Identity\Http\Controllers\AccountRecoveryController;
use App\Modules\Identity\Http\Controllers\AuthenticatedSessionController;
use App\Modules\Identity\Http\Controllers\IdentityDashboardController;
use App\Modules\Identity\Http\Controllers\InvitationAcceptanceController;
use App\Modules\Identity\Http\Controllers\PasswordChangeController;
use App\Modules\Identity\Http\Controllers\UserManagementController;
use App\Modules\People\Http\Controllers\PeopleManagementController;
use App\Modules\People\Http\Controllers\StudentDirectoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('identity.login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('identity.login.store');
    Route::get('/staff/login', [AuthenticatedSessionController::class, 'createStaff'])->name('identity.staff.login');
    Route::post('/staff/login', [AuthenticatedSessionController::class, 'storeStaff'])->name('identity.staff.login.store');
    Route::get('/account-recovery', [AccountRecoveryController::class, 'create'])->name('identity.recovery');
    Route::get('/activate-account', [InvitationAcceptanceController::class, 'activation'])->name('identity.invitations.activate');
    Route::post('/activate-account', [InvitationAcceptanceController::class, 'activate'])
        ->middleware('throttle:10,1')
        ->name('identity.invitations.activate.store');
    Route::get('/i/{token}', [InvitationAcceptanceController::class, 'createShort'])
        ->middleware('throttle:30,1')
        ->name('identity.invitations.short');
    Route::post('/i/{token}', [InvitationAcceptanceController::class, 'storeShort'])
        ->middleware('throttle:10,1')
        ->name('identity.invitations.short.store');
    Route::get('/invitations/{invitation}/{token}', [InvitationAcceptanceController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('identity.invitations.show');
    Route::post('/invitations/{invitation}/{token}', [InvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('identity.invitations.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('identity.logout');
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('identity.password.edit');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('identity.password.update');
});

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/dashboard', IdentityDashboardController::class)->name('identity.dashboard');

    Route::prefix('admin')->middleware('role:admin')->group(function (): void {
        Route::get('/users', [UserManagementController::class, 'index'])->name('identity.admin.users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('identity.admin.users.store');
        Route::put('/users/{user}/roles', [UserManagementController::class, 'updateRoles'])->name('identity.admin.users.roles');
        Route::post('/users/{user}/recovery-invitation', [UserManagementController::class, 'issueRecoveryInvitation'])->name('identity.admin.users.recovery');
        Route::post('/users/{user}/reactivate-invitation', [UserManagementController::class, 'reactivate'])->name('identity.admin.users.reactivate');
        Route::delete('/users/{user}', [UserManagementController::class, 'disable'])->name('identity.admin.users.disable');

        Route::get('/people', [PeopleManagementController::class, 'index'])->name('people.admin.index');
        Route::post('/guardians', [PeopleManagementController::class, 'storeGuardian'])->name('people.admin.guardians.store');
        Route::post('/students', [PeopleManagementController::class, 'storeStudent'])->name('people.admin.students.store');
        Route::delete('/students/{student}', [PeopleManagementController::class, 'destroyStudent'])->name('people.admin.students.destroy');
        Route::put('/students/{student}/account', [PeopleManagementController::class, 'linkStudentAccount'])->name('people.admin.students.account');
        Route::delete('/students/{student}/account', [PeopleManagementController::class, 'disconnectStudentAccount'])->name('people.admin.students.account.disconnect');
        Route::post('/guardian-student-links', [PeopleManagementController::class, 'link'])->name('people.admin.links.store');
        Route::post('/guardians/{guardian}/students/{student}/verify', [PeopleManagementController::class, 'verify'])->name('people.admin.links.verify');
    });

    Route::prefix('registrar')->middleware('role:admin,registrar')->group(function (): void {
        // Bare /registrar has no landing page of its own; send staff to the queue.
        Route::redirect('/', '/registrar/enrollments')->name('registrar.home');
        Route::get('/students', [StudentDirectoryController::class, 'index'])->name('people.registrar.students.index');
        Route::get('/students/{student}', [StudentDirectoryController::class, 'show'])->name('people.registrar.students.show');
    });
});
