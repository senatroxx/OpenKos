<?php

namespace OpenKOS\Platform\Notification;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A notification driver made available to the platform. The registry holds
 * these descriptors (not live instances) so drivers are instantiated lazily
 * with their resolved credentials by the owning channel/manager.
 *
 * `driverClass` is a plain class-string rather than a typed contract because
 * each channel brings its own driver interface shaped to its needs — e.g.
 * WhatsApp drivers implement App\Contracts\WhatsAppDriver (stateful: pairing,
 * health). A future SMS channel would define App\Contracts\SmsDriver and its
 * own manager the same way. The channel owns the type.
 */
final readonly class NotificationDriverRegistration implements Arrayable
{
    public function __construct(
        public string $name,
        public string $channel,
        public string $driverClass,
        public string $label,
        public array $config = [],
    ) {}

    public function toArray(): array
    {
        // Credentials (config) and class are never exposed to the frontend.
        return [
            'name' => $this->name,
            'channel' => $this->channel,
            'label' => $this->label,
        ];
    }
}
