<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RoleController;
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

    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index')->middleware('permission:properties.view');
    Route::post('properties', [PropertyController::class, 'store'])->name('properties.store')->middleware('permission:properties.create');
    Route::put('properties/{property}', [PropertyController::class, 'update'])->name('properties.update')->middleware('permission:properties.update');
    Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy')->middleware('permission:properties.delete');

    Route::get('properties/{property}/rooms', [RoomController::class, 'index'])->name('properties.rooms.index')->middleware('permission:rooms.view');
    Route::post('properties/{property}/rooms', [RoomController::class, 'store'])->name('properties.rooms.store')->middleware('permission:rooms.create');
    Route::put('properties/{property}/rooms/{room}', [RoomController::class, 'update'])->name('properties.rooms.update')->middleware('permission:rooms.update');
    Route::delete('properties/{property}/rooms/{room}', [RoomController::class, 'destroy'])->name('properties.rooms.destroy')->middleware('permission:rooms.delete');

    Route::get('properties/{property}/rooms/{room}/leases', [LeaseController::class, 'index'])->name('properties.rooms.leases.index')->middleware('permission:leases.view');
    Route::post('properties/{property}/rooms/{room}/leases', [LeaseController::class, 'store'])->name('properties.rooms.leases.store')->middleware('permission:leases.create');
    Route::put('properties/{property}/rooms/{room}/leases/{lease}', [LeaseController::class, 'update'])->name('properties.rooms.leases.update')->middleware('permission:leases.update');
    Route::delete('properties/{property}/rooms/{room}/leases/{lease}', [LeaseController::class, 'destroy'])->name('properties.rooms.leases.destroy')->middleware('permission:leases.delete');
    Route::post('properties/{property}/rooms/{room}/leases/{lease}/move', [LeaseController::class, 'move'])->name('properties.rooms.leases.move')->middleware('permission:leases.move');

    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index')->middleware('permission:tenants.view');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store')->middleware('permission:tenants.create');
    Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update')->middleware('permission:tenants.update');
    Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy')->middleware('permission:tenants.delete');
    Route::post('tenants/{tenant}/assign-room', [TenantController::class, 'assignRoom'])->name('tenants.assign-room')->middleware('permission:tenants.update');
    Route::post('leases/{lease}/move-out', [LeaseController::class, 'moveOut'])->name('leases.move-out')->middleware('permission:leases.move_out');
    Route::get('leases', [LeaseController::class, 'globalIndex'])->name('leases.index')->middleware('permission:leases.view');

    Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('permission:users.view');
    Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('permission:users.create');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:users.delete');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password')->middleware('permission:users.reset_password');
    Route::post('users/{user}/resend-invitation', [UserController::class, 'resendInvitation'])->name('users.resend-invitation')->middleware('permission:users.resend_invitation');

    Route::middleware('role:owner')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('roles/{role}/clone', [RoleController::class, 'clone'])->name('roles.clone');
    });
});

require __DIR__.'/settings.php';
