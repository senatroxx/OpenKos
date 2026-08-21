<?php

use App\Http\Controllers\Settings\AboutController;
use App\Http\Controllers\Settings\GeneralController;
use App\Http\Controllers\Settings\MailController;
use App\Http\Controllers\Settings\PaymentGatewayController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\PropertyTypeController;
use App\Http\Controllers\Settings\ReminderController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SettingValuesController;
use App\Http\Controllers\Settings\WhatsAppController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/about', [AboutController::class, 'edit'])->name('settings.about.edit');
    Route::get('settings/about/license', [AboutController::class, 'license'])->name('settings.about.license');

    Route::middleware('role:owner')->group(function () {
        Route::get('settings/general', [GeneralController::class, 'edit'])->name('settings.general.edit');
        Route::patch('settings/general', [GeneralController::class, 'update'])->name('settings.general.update');

        Route::get('settings/payment-gateway', [PaymentGatewayController::class, 'edit'])->name('settings.payment-gateway.edit');
        Route::patch('settings/payment-gateway', [PaymentGatewayController::class, 'update'])->name('settings.payment-gateway.update');

        Route::get('settings/reminders', [ReminderController::class, 'edit'])->name('settings.reminders.edit');
        Route::patch('settings/reminders', [ReminderController::class, 'update'])->name('settings.reminders.update');

        Route::post('settings/values', [SettingValuesController::class, 'upsert'])->name('settings.values.upsert');
        Route::get('settings/mail', [MailController::class, 'edit'])->name('settings.mail.edit');
        Route::patch('settings/mail', [MailController::class, 'update'])->name('settings.mail.update');
        Route::post('settings/mail/test', [MailController::class, 'test'])->name('settings.mail.test');

        Route::get('settings/whatsapp', [WhatsAppController::class, 'edit'])->name('settings.whatsapp.edit');
        Route::patch('settings/whatsapp', [WhatsAppController::class, 'update'])->name('settings.whatsapp.update');
        Route::post('settings/whatsapp/test', [WhatsAppController::class, 'test'])->name('settings.whatsapp.test');
        Route::get('settings/whatsapp/status', [WhatsAppController::class, 'status'])->name('settings.whatsapp.status');

        Route::get('settings/property-types', [PropertyTypeController::class, 'index'])->name('settings.property-types.index');
        Route::post('settings/property-types', [PropertyTypeController::class, 'store'])->name('settings.property-types.store');
        Route::patch('settings/property-types/{propertyType}', [PropertyTypeController::class, 'update'])->name('settings.property-types.update');
        Route::delete('settings/property-types/{propertyType}', [PropertyTypeController::class, 'destroy'])->name('settings.property-types.destroy');

        // Catch-all for plugin-defined settings pages — must be last so explicit routes match first.
        Route::get('settings/{page}', [SettingValuesController::class, 'edit'])->name('settings.dynamic.edit');
    });
});
