<?php

use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PublicListingController;
use App\Http\Controllers\PublicListingMediaController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/payment/{gateway}', PaymentWebhookController::class)
    ->where('gateway', '.+')
    ->name('webhooks.payment');

Route::prefix('v1/listings')->name('public.listings.')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [PublicListingController::class, 'index'])->name('index');
    Route::get('media/{media}', [PublicListingMediaController::class, 'show'])
        ->whereNumber('media')
        ->name('media');
    Route::get('{property:public_slug}', [PublicListingController::class, 'show'])->name('show');
    Route::get('{property:public_slug}/unit-types/{unitType:public_slug}', [PublicListingController::class, 'unitType'])
        ->scopeBindings()
        ->name('unit-types.show');
});
