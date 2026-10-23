<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/bank-statements/dashboard', [App\Http\Controllers\RentDashboardController::class, 'index'])
        ->name('bank-statements.dashboard');

    Route::get('/bank-statements/dashboard/{property}', [App\Http\Controllers\RentDashboardController::class, 'show'])
        ->name('bank-statements.dashboard.property');
});
