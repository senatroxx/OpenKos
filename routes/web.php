<?php

use App\Http\Controllers\LeaseController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'permission:dashboard.view'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

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
});

require __DIR__.'/settings.php';
