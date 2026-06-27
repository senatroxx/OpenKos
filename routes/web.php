<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\LeaseRentScheduleController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantDocumentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('invitations/{token}', [UserController::class, 'acceptInvitation'])
    ->middleware('guest')
    ->name('users.invitations.accept');

Route::post('invitations/accept', [UserController::class, 'completeInvitation'])
    ->middleware('guest')
    ->name('users.invitations.complete');

Route::middleware(['auth', 'verified', 'permission:dashboard.view'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('properties')->name('properties.')->group(function () {
        Route::get('/', [PropertyController::class, 'index'])->name('index')->middleware('permission:properties.view');
        Route::post('/', [PropertyController::class, 'store'])->name('store')->middleware('permission:properties.create');
        Route::put('{property}', [PropertyController::class, 'update'])->name('update')->middleware('permission:properties.update');
        Route::delete('{property}', [PropertyController::class, 'destroy'])->name('destroy')->middleware('permission:properties.delete');
    });

    Route::scopeBindings()->group(function () {
        Route::prefix('properties/{property}')->name('properties.')->group(function () {
            Route::prefix('rooms')->name('rooms.')->group(function () {
                Route::get('/', [RoomController::class, 'index'])->name('index')->middleware('permission:rooms.view');
                Route::post('/', [RoomController::class, 'store'])->name('store')->middleware('permission:rooms.create');
                Route::put('{room}', [RoomController::class, 'update'])->name('update')->middleware('permission:rooms.update');
                Route::delete('{room}', [RoomController::class, 'destroy'])->name('destroy')->middleware('permission:rooms.delete');

                Route::prefix('{room}/leases')->name('leases.')->group(function () {
                    Route::get('/', [LeaseController::class, 'index'])->name('index')->middleware('permission:leases.view');
                    Route::post('/', [LeaseController::class, 'store'])->name('store')->middleware('permission:leases.create');
                    Route::put('{lease}', [LeaseController::class, 'update'])->name('update')->middleware('permission:leases.update');
                    Route::delete('{lease}', [LeaseController::class, 'destroy'])->name('destroy')->middleware('permission:leases.delete');
                    Route::post('{lease}/move', [LeaseController::class, 'move'])->name('move')->middleware('permission:leases.move');
                });
            });
        });
    });

    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', [TenantController::class, 'index'])->name('index')->middleware('permission:tenants.view');
        Route::post('/', [TenantController::class, 'store'])->name('store')->middleware('permission:tenants.create');
        Route::put('{tenant}', [TenantController::class, 'update'])->name('update')->middleware('permission:tenants.update');
        Route::delete('{tenant}', [TenantController::class, 'destroy'])->name('destroy')->middleware('permission:tenants.delete');
        Route::post('{tenant}/assign-room', [TenantController::class, 'assignRoom'])->name('assign-room')->middleware('permission:tenants.update');

        Route::post('{tenant}/documents', [TenantDocumentController::class, 'store'])->name('documents.store')->middleware('permission:tenants.update');
        Route::get('{tenant}/documents/{document}', [TenantDocumentController::class, 'show'])->name('documents.show');
        Route::delete('{tenant}/documents/{document}', [TenantDocumentController::class, 'destroy'])->name('documents.destroy')->middleware('permission:tenants.update');
    });

    Route::prefix('leases')->name('leases.')->group(function () {
        Route::get('/', [LeaseController::class, 'globalIndex'])->name('index')->middleware('permission:leases.view');
        Route::get('{lease}/rent-schedule', LeaseRentScheduleController::class)->name('rent-schedule')->middleware('permission:leases.view');
        Route::post('{lease}/move-out', [LeaseController::class, 'moveOut'])->name('move-out')->middleware('permission:leases.move_out');
        Route::post('{lease}/payments', [PaymentController::class, 'store'])->name('payments.store')->middleware('permission:payments.create');
    });

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index')->middleware('permission:users.view');
        Route::post('/', [UserController::class, 'store'])->name('store')->middleware('permission:users.create');
        Route::put('{user}', [UserController::class, 'update'])->name('update')->middleware('permission:users.update');
        Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy')->middleware('permission:users.delete');
        Route::post('{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password')->middleware('permission:users.reset_password');
        Route::post('{user}/resend-invitation', [UserController::class, 'resendInvitation'])->name('resend-invitation')->middleware('permission:users.resend_invitation');
    });

    Route::middleware('role:owner')->group(function () {
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::get('create', [RoleController::class, 'create'])->name('create');
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::get('{role}/edit', [RoleController::class, 'edit'])->name('edit');
            Route::put('{role}', [RoleController::class, 'update'])->name('update');
            Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
            Route::post('{role}/clone', [RoleController::class, 'clone'])->name('clone');
        });
    });
});

require __DIR__.'/settings.php';
