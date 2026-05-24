<?php

use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RoomController;
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
    });
});

require __DIR__.'/settings.php';
