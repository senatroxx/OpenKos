<?php

namespace OpenKOS\Core\Contracts;

/**
 * Channel-agnostic notification driver contract for platform plugins.
 *
 * Existing WhatsApp drivers (App\Contracts\WhatsAppDriver) stay untouched;
 * a future adapter can wrap them to satisfy this interface.
 */
interface NotificationDriver
{
    public function name(): string;

    /**
     * The channel this driver delivers to, e.g. 'whatsapp', 'email', 'sms'.
     */
    public function channel(): string;

    public function send(string $recipient, string $message, array $options = []): void;

    /**
     * Describe the configuration fields this driver needs.
     * Mirrors the App\Contracts\WhatsAppDriver::configurationSchema() convention.
     */
    public function configurationSchema(): array;
}
