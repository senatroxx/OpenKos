<?php

use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\Settings\AboutController;
use App\Http\Controllers\Settings\GeneralController;
use App\Http\Controllers\Settings\MailController;
use App\Http\Controllers\Settings\PaymentGatewayController;
use App\Http\Controllers\Settings\PluginController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\PropertyTypeController;
use App\Http\Controllers\Settings\ReminderController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SettingValuesController;
use App\Http\Controllers\Settings\WhatsAppController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::get('branding/{asset}', BrandingAssetController::class)
    ->whereIn('asset', ['logo', 'favicon'])
    ->name('branding.asset');

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
        Route::post('settings/general/branding/{asset}', [GeneralController::class, 'updateBranding'])
            ->whereIn('asset', ['logo', 'favicon'])
            ->name('settings.general.branding.update');
        Route::delete('settings/general/branding/{asset}', [GeneralController::class, 'removeBranding'])
            ->whereIn('asset', ['logo', 'favicon'])
            ->name('settings.general.branding.destroy');

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

        Route::get('settings/plugins', [PluginController::class, 'index'])->name('settings.plugins.index');
        Route::post('settings/plugins', [PluginController::class, 'install'])->name('settings.plugins.install');
        Route::post('settings/plugins/{vendor}/{package}/enable', [PluginController::class, 'enable'])
            ->where(['vendor' => '[a-z0-9][a-z0-9._-]*', 'package' => '[a-z0-9][a-z0-9._-]*'])
            ->name('settings.plugins.enable');
        Route::post('settings/plugins/{vendor}/{package}/disable', [PluginController::class, 'disable'])
            ->where(['vendor' => '[a-z0-9][a-z0-9._-]*', 'package' => '[a-z0-9][a-z0-9._-]*'])
            ->name('settings.plugins.disable');
        Route::delete('settings/plugins/{vendor}/{package}', [PluginController::class, 'destroy'])
            ->where(['vendor' => '[a-z0-9][a-z0-9._-]*', 'package' => '[a-z0-9][a-z0-9._-]*'])
            ->name('settings.plugins.destroy');

        // Catch-all for plugin-defined settings pages — must be last so explicit routes match first.
        Route::get('settings/{page}', [SettingValuesController::class, 'edit'])->name('settings.dynamic.edit');
    });
});
