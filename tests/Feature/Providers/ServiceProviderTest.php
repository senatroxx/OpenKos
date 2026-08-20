<?php

use App\Exceptions\MailDriverNotFoundException;
use App\Models\Setting;
use App\Notifications\Drivers\LogMailDriver;
use App\Notifications\UserInvitation;
use App\Providers\AppServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Services\MailManager;
use App\Services\Platform\ComposerPluginDiscovery;
use App\Services\Settings\PlatformSettingsStore;
use App\Services\Settings\SettingManager;
use App\Services\WhatsAppManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Core\Contracts\SettingsStore;
use OpenKOS\Core\Data\Mail\MailAddress;
use OpenKOS\Core\Data\Mail\MailMessage;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;

it('configures a valid display timezone without changing the application timezone', function () {
    Setting::set('timezone', 'America/New_York');
    app()->forgetScopedInstances();

    (new AppServiceProvider(app()))->boot();

    expect(config('app.display_timezone'))->toBe('America/New_York')
        ->and(config('app.timezone'))->toBe('UTC');
});

it('falls back to UTC for an invalid persisted display timezone', function () {
    Setting::set('timezone', 'invalid/timezone');
    app()->forgetScopedInstances();

    (new AppServiceProvider(app()))->boot();

    expect(config('app.display_timezone'))->toBe('UTC');
});

it('boots with UTC when the settings table is unavailable', function () {
    Schema::dropIfExists('settings');
    app()->forgetScopedInstances();

    (new AppServiceProvider(app()))->boot();

    expect(config('app.display_timezone'))->toBe('UTC');
});

it('registers application services through dedicated providers', function () {
    expect(app(SettingManager::class))
        ->toBe(app(SettingManager::class))
        ->and(app(SettingsStore::class))
        ->toBeInstanceOf(PlatformSettingsStore::class)
        ->and(app(PluginDiscovery::class))
        ->toBeInstanceOf(ComposerPluginDiscovery::class)
        ->and(app(MailManager::class))
        ->toBe(app(MailManager::class))
        ->and(app(WhatsAppManager::class))
        ->toBe(app(WhatsAppManager::class));
});

it('maps OpenKOS mail drivers to valid Laravel mailers', function () {
    Setting::set('mail_config', ['driver' => 'openkos/smtp']);

    (new NotificationServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers'))->toHaveKey(config('mail.default'));
});

it('uses an advertised Laravel mailer for a registered OpenKOS driver', function () {
    config(['mail.mailers.test-advertised' => ['transport' => 'log']]);
    app(NotificationRegistry::class)->registerDriver(new NotificationDriverRegistration(
        name: 'test/advertised',
        channel: 'mail',
        driverClass: LogMailDriver::class,
        label: 'Test Advertised',
        laravelMailer: 'test-advertised',
    ));
    Setting::set('mail_config', ['driver' => 'test/advertised']);

    (new NotificationServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('test-advertised')
        ->and(config('mail.mailers'))->toHaveKey(config('mail.default'));

    Notification::route('mail', 'user@example.com')
        ->notify(new UserInvitation('https://example.com/invitation'));
});

it('falls back with a capability warning for an OpenKOS-only mail driver', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('OpenKOS mail driver has no Laravel mailer capability; falling back to Laravel log mailer.', [
            'driver' => 'openkos/acme',
            'fallback' => 'log',
            'reason' => 'missing_capability',
        ]);
    app(NotificationRegistry::class)->registerDriver(new NotificationDriverRegistration(
        name: 'openkos/acme',
        channel: 'mail',
        driverClass: LogMailDriver::class,
        label: 'Acme',
    ));
    Setting::set('mail_config', ['driver' => 'openkos/acme']);

    (new NotificationServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('log')
        ->and(config('mail.mailers'))->toHaveKey(config('mail.default'));
});

it('falls back with an invalid-capability warning when the advertised mailer is unavailable', function () {
    $mailers = config('mail.mailers');
    unset($mailers['test-advertised']);
    config(['mail.mailers' => $mailers]);

    Log::shouldReceive('warning')
        ->once()
        ->with('Configured Laravel mailer for OpenKOS mail driver is unavailable; falling back to Laravel log mailer.', [
            'driver' => 'test/advertised',
            'mailer' => 'test-advertised',
            'fallback' => 'log',
            'reason' => 'invalid_capability',
        ]);
    app(NotificationRegistry::class)->registerDriver(new NotificationDriverRegistration(
        name: 'test/advertised',
        channel: 'mail',
        driverClass: LogMailDriver::class,
        label: 'Test Advertised',
        laravelMailer: 'test-advertised',
    ));
    Setting::set('mail_config', ['driver' => 'test/advertised']);

    (new NotificationServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('log')
        ->and(config('mail.mailers'))->toHaveKey(config('mail.default'));
});

it('preserves configured native Laravel mailers', function () {
    config(['mail.mailers.ses' => ['transport' => 'log']]);
    Setting::set('mail_config', ['driver' => 'ses']);

    (new NotificationServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('ses')
        ->and(config('mail.mailers'))->toHaveKey(config('mail.default'));

    Notification::route('mail', 'user@example.com')
        ->notify(new UserInvitation('https://example.com/invitation'));
});

it('warns and falls back to Laravel log when a persisted external driver is absent', function () {
    app()->instance(NotificationRegistry::class, new NotificationRegistry);

    Log::shouldReceive('warning')
        ->once()
        ->with('Unknown mail driver; falling back to Laravel log mailer.', [
            'driver' => 'openkos/acme',
            'fallback' => 'log',
            'reason' => 'unknown_driver',
        ]);
    Setting::set('mail_config', ['driver' => 'openkos/acme']);

    (new NotificationServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('log')
        ->and(config('mail.mailers'))->toHaveKey(config('mail.default'));
});

it('fails deterministically for custom delivery when a persisted external driver is absent', function () {
    app()->instance(NotificationRegistry::class, new NotificationRegistry);
    Setting::set('mail_config', ['driver' => 'openkos/acme']);

    $message = new MailMessage(
        to: [new MailAddress('user@example.com')],
        subject: 'Test Subject',
        htmlBody: '<p>Hello</p>',
    );

    expect(fn () => app(MailManager::class)->send($message))
        ->toThrow(MailDriverNotFoundException::class, 'openkos/acme');
});
