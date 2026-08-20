<?php

use App\Events\WhatsApp\WhatsAppFailed;
use App\Events\WhatsApp\WhatsAppSent;
use App\Exceptions\WhatsAppDeliveryException;
use App\Exceptions\WhatsAppDriverNotFoundException;
use App\Models\Setting;
use App\Services\WhatsAppManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use OpenKOS\Core\Data\WhatsApp\WhatsAppAttachment;
use OpenKOS\Core\Data\WhatsApp\WhatsAppContent;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;
use Tests\Support\Fakes\UnitTestFailingWhatsAppDriver;
use Tests\Support\Fakes\UnitTestUnsupportedWhatsAppDriver;
use Tests\Support\Fakes\UnitTestWhatsAppDriver;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('WhatsAppManager', function () {
    it('normalizes legacy driver aliases to namespaced driver IDs', function () {
        $manager = app(WhatsAppManager::class);

        expect($manager->normalizeDriverId('log'))->toBe('openkos/whatsapp-log');
        expect($manager->normalizeDriverId('openkos/log'))->toBe('openkos/whatsapp-log');
        expect($manager->normalizeDriverId('fonnte'))->toBe('openkos/fonnte');
        expect($manager->normalizeDriverId('custom/driver'))->toBe('custom/driver');
    });

    it('resolves registered driver via container', function () {
        $registry = app(NotificationRegistry::class);
        $registry->registerDriver(new NotificationDriverRegistration(
            name: 'test/whatsapp-custom',
            channel: 'whatsapp',
            driverClass: UnitTestWhatsAppDriver::class,
            label: 'Test Driver',
        ));

        $manager = new WhatsAppManager($registry, app());

        $driver = $manager->driver('test/whatsapp-custom');

        expect($driver)->toBeInstanceOf(UnitTestWhatsAppDriver::class);
    });

    it('throws exception when driver is not registered', function () {
        $registry = new NotificationRegistry;
        $manager = new WhatsAppManager($registry, app());

        expect(fn () => $manager->driver('non-existent'))
            ->toThrow(WhatsAppDriverNotFoundException::class);
    });

    it('dispatches WhatsAppSent event on successful send', function () {
        Event::fake([WhatsAppSent::class]);
        Setting::set('whatsapp_driver', 'log');

        $manager = app(WhatsAppManager::class);
        $manager->send('628123456789', 'Hello WhatsApp');

        Event::assertDispatched(WhatsAppSent::class, function ($event) {
            return $event->driverId === 'openkos/whatsapp-log'
                && $event->phone === '628123456789';
        });
    });

    it('forwards document attachments to the driver', function () {
        app(NotificationRegistry::class)->registerDriver(new NotificationDriverRegistration(
            name: 'test/capture',
            channel: 'whatsapp',
            driverClass: UnitTestWhatsAppDriver::class,
            label: 'Capture Driver',
        ));
        Setting::set('whatsapp_driver', 'test/capture');
        $attachment = new WhatsAppAttachment('%PDF-test', 'invoice.pdf', 'application/pdf');

        app(WhatsAppManager::class)->send('628123456789', new WhatsAppContent('Invoice', attachment: $attachment));

        expect(UnitTestWhatsAppDriver::$lastMessage?->attachment)->toBe($attachment);
    });

    it('fails explicitly when a driver does not support attachments', function () {
        app(NotificationRegistry::class)->registerDriver(new NotificationDriverRegistration(
            name: 'test/unsupported',
            channel: 'whatsapp',
            driverClass: UnitTestUnsupportedWhatsAppDriver::class,
            label: 'Unsupported Driver',
        ));
        Setting::set('whatsapp_driver', 'test/unsupported');

        expect(fn () => app(WhatsAppManager::class)->send(
            '628123456789',
            new WhatsAppContent('Invoice', attachment: new WhatsAppAttachment('%PDF-test', 'invoice.pdf', 'application/pdf')),
        ))->toThrow(WhatsAppDeliveryException::class, 'does not support document attachments');
    });

    it('dispatches WhatsAppFailed event and throws WhatsAppDeliveryException on failure', function () {
        Event::fake([WhatsAppFailed::class]);

        $registry = app(NotificationRegistry::class);
        $registry->registerDriver(new NotificationDriverRegistration(
            name: 'openkos/failing',
            channel: 'whatsapp',
            driverClass: UnitTestFailingWhatsAppDriver::class,
            label: 'Failing Driver',
        ));

        Setting::set('whatsapp_driver', 'openkos/failing');

        $manager = new WhatsAppManager($registry, app());

        expect(fn () => $manager->send('628123456789', 'Failing Message'))
            ->toThrow(WhatsAppDeliveryException::class);

        Event::assertDispatched(WhatsAppFailed::class, function ($event) {
            return $event->driverId === 'openkos/failing'
                && $event->phone === '628123456789';
        });
    });
});
