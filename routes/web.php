<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TenantController;
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

    Route::middleware('permission:properties.manage')->group(function () {
        Route::resource('properties', PropertyController::class)->only([
            'index', 'store', 'update', 'destroy',
        ]);

        Route::resource('properties.rooms', RoomController::class)->scoped()->only([
            'index', 'store', 'update', 'destroy',
        ]);

        Route::resource('properties.rooms.leases', LeaseController::class)->scoped()->only([
            'index', 'store', 'update', 'destroy',
        ]);

        Route::post(
            'properties/{property}/rooms/{room}/leases/{lease}/move',
            [LeaseController::class, 'move'],
        )->name('properties.rooms.leases.move');
    });

    Route::middleware('permission:tenants.manage')->group(function () {
        Route::resource('tenants', TenantController::class)->only([
            'index', 'store', 'update', 'destroy',
        ]);

        Route::post('tenants/{tenant}/assign-room', [TenantController::class, 'assignRoom'])
            ->name('tenants.assign-room');

        Route::post('leases/{lease}/move-out', [LeaseController::class, 'moveOut'])
            ->name('leases.move-out');

        Route::get('leases', [LeaseController::class, 'globalIndex'])->name('leases.index');
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::resource('users', UserController::class)->only([
            'index', 'store', 'update', 'destroy',
        ]);

        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('users.reset-password');

        Route::post('users/{user}/resend-invitation', [UserController::class, 'resendInvitation'])
            ->name('users.resend-invitation');
    });
});

require __DIR__.'/settings.php';
