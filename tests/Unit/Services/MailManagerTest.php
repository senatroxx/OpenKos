<?php

use App\Events\Mail\MailFailed;
use App\Events\Mail\MailSent;
use App\Exceptions\MailDeliveryException;
use App\Exceptions\MailDriverNotFoundException;
use App\Models\Setting;
use App\Notifications\Drivers\SmtpMailDriver;
use App\Services\MailManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use OpenKOS\Core\Data\Mail\MailAddress;
use OpenKOS\Core\Data\Mail\MailMessage;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;
use Tests\Support\Fakes\MailManagerConfigProbeDriver;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function registerMailManagerConfigProbe(array $config = []): void
{
    app(NotificationRegistry::class)->registerDriver(new NotificationDriverRegistration(
        name: 'test/config-probe',
        channel: 'mail',
        driverClass: MailManagerConfigProbeDriver::class,
        label: 'Config Probe',
        config: $config,
    ));
}

test('mail manager resolves driver using normalizeDriverId alias mapping', function () {
    Setting::set('mail_config', ['driver' => 'smtp', 'host' => '127.0.0.1', 'port' => 1025]);

    $manager = app(MailManager::class);
    $driver = $manager->driver();

    expect($driver)->toBeInstanceOf(SmtpMailDriver::class);
});

test('mail manager passes registration defaults to third-party drivers', function () {
    MailManagerConfigProbeDriver::$configs = [];
    registerMailManagerConfigProbe(['api_key' => 'registration-key']);
    Setting::set('mail_config', ['driver' => 'test/config-probe']);

    app(MailManager::class)->driver();

    expect(MailManagerConfigProbeDriver::$configs[0]['api_key'])->toBe('registration-key');
});

test('stored driver configuration overrides registration defaults', function () {
    MailManagerConfigProbeDriver::$configs = [];
    registerMailManagerConfigProbe(['api_key' => 'registration-key']);
    Setting::set('mail_config', [
        'driver' => 'test/config-probe',
        'drivers' => [
            'test/config-probe' => ['api_key' => 'stored-key'],
        ],
        'from' => ['address' => 'global@example.com'],
    ]);

    app(MailManager::class)->driver();

    expect(MailManagerConfigProbeDriver::$configs[0]['api_key'])->toBe('stored-key');
});

test('effective global sender configuration overrides driver-specific sender configuration', function () {
    MailManagerConfigProbeDriver::$configs = [];
    registerMailManagerConfigProbe([
        'from' => ['address' => 'registration@example.com'],
    ]);
    Setting::set('mail_config', [
        'driver' => 'test/config-probe',
        'drivers' => [
            'test/config-probe' => [
                'from' => ['address' => 'driver@example.com'],
            ],
        ],
        'from' => ['address' => 'global@example.com'],
    ]);

    app(MailManager::class)->driver();

    expect(MailManagerConfigProbeDriver::$configs[0]['from'])->toBe(['address' => 'global@example.com']);
});

test('mail manager dispatches MailSent event on successful send', function () {
    Event::fake();
    Setting::set('mail_config', ['driver' => 'log']);

    $manager = app(MailManager::class);
    $message = new MailMessage(
        to: [new MailAddress('user@example.com')],
        subject: 'Test Subject',
        htmlBody: '<p>Hello</p>',
    );

    $manager->send($message);

    Event::assertDispatched(MailSent::class, function (MailSent $event) {
        return $event->driver === 'openkos/log'
            && $event->recipients === ['user@example.com']
            && $event->subject === 'Test Subject';
    });
});

test('mail manager dispatches MailFailed event and throws MailDeliveryException on failure', function () {
    Event::fake();
    Setting::set('mail_config', ['driver' => 'smtp', 'host' => '']);

    $manager = app(MailManager::class);
    $message = new MailMessage(
        to: [new MailAddress('user@example.com')],
        subject: 'Failing Subject',
        htmlBody: '<p>Hello</p>',
    );

    expect(fn () => $manager->send($message))->toThrow(MailDeliveryException::class);

    Event::assertDispatched(MailFailed::class, function (MailFailed $event) {
        return $event->driver === 'openkos/smtp'
            && $event->recipients === ['user@example.com']
            && $event->subject === 'Failing Subject'
            && $event->exception instanceof MailDeliveryException;
    });
});

test('mail manager throws MailDriverNotFoundException when driver is unregistered', function () {
    Setting::set('mail_config', ['driver' => 'nonexistent/driver']);

    $manager = app(MailManager::class);
    $message = new MailMessage(
        to: [new MailAddress('user@example.com')],
        subject: 'Test',
        htmlBody: 'Body',
    );

    expect(fn () => $manager->send($message))->toThrow(MailDriverNotFoundException::class);
});
