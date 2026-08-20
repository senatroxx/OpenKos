<?php

namespace Tests\Support\Fakes;

use OpenKOS\Core\Contracts\WhatsAppDriver;
use OpenKOS\Core\Data\WhatsApp\DriverHealthResult;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;
use RuntimeException;

class UnitTestFailingWhatsAppDriver implements WhatsAppDriver
{
    public function __construct(public array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        throw new RuntimeException('Network connection failed');
    }

    public function supportsAttachments(): bool
    {
        return true;
    }

    public function health(): DriverHealthResult
    {
        return new DriverHealthResult(false, 'Unhealthy');
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
