<?php

namespace Tests\Support\Fakes;

use OpenKOS\Core\Contracts\WhatsAppDriver;
use OpenKOS\Core\Data\WhatsApp\DriverHealthResult;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;

class UnitTestUnsupportedWhatsAppDriver implements WhatsAppDriver
{
    public function __construct(public array $config = []) {}

    public function send(WhatsAppMessage $message): void {}

    public function supportsAttachments(): bool
    {
        return false;
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
