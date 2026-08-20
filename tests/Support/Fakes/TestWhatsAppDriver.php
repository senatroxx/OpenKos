<?php

namespace Tests\Support\Fakes;

use OpenKOS\Core\Contracts\WhatsAppDriver;
use OpenKOS\Core\Data\WhatsApp\DriverHealthResult;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;

class TestWhatsAppDriver implements WhatsAppDriver
{
    public static array $sentMessages = [];

    public function __construct(private array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        self::$sentMessages[] = $message->phone;
    }

    public function supportsAttachments(): bool
    {
        return true;
    }

    public function health(): DriverHealthResult
    {
        return new DriverHealthResult(true);
    }

    public function supportsPairing(): bool
    {
        return false;
    }

    public function configurationSchema(): array
    {
        return [];
    }

    public function getPairingQrCode(): ?string
    {
        return null;
    }

    public function pair(): void {}

    public function disconnect(): void {}
}
