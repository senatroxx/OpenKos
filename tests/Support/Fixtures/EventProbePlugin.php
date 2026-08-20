<?php

namespace Tests\Support\Fixtures;

use OpenKOS\Core\Events\PaymentRecorded;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

class EventProbePlugin extends Plugin
{
    public static bool $fired = false;

    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/event', name: 'Event', version: '1.0.0');
    }

    public function register(OpenKOSManager $platform): void {}

    public function listens(): array
    {
        return [
            PaymentRecorded::class => fn (PaymentRecorded $event) => static::$fired = true,
        ];
    }
}
