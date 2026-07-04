<?php

namespace OpenKOS\Plugins\WhatsApp;

use Illuminate\Support\Arr;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;

/**
 * Core plugin that registers the built-in WhatsApp drivers into the platform
 * NotificationRegistry. Realises the spec's intent that "current WhatsApp
 * implementations should become plugins": WhatsAppManager and the settings
 * page now read drivers from the registry, and third-party plugins can add
 * more by registering their own whatsapp-channel drivers.
 */
class WhatsAppPlugin extends Plugin
{
    public function register(OpenKOSManager $platform): void
    {
        foreach (config('services.whatsapp.drivers', []) as $name => $definition) {
            $platform->notifications()->registerDriver(new NotificationDriverRegistration(
                name: $name,
                channel: 'whatsapp',
                driverClass: $definition['class'],
                label: $definition['label'] ?? $name,
                config: Arr::except($definition, ['class', 'label']),
            ));
        }
    }
}
