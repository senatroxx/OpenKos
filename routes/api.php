<?php

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/payment/{gateway}', PaymentWebhookController::class)
    ->where('gateway', '.+')
    ->name('webhooks.payment');
