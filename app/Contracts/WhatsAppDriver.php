<?php

namespace App\Contracts;

use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;

interface WhatsAppDriver
{
    public function send(WhatsAppMessage $message): void;

    public function health(): DriverHealthResult;

    public function supportsPairing(): bool;

    public function configurationSchema(): array;

    public function getPairingQrCode(): ?string;
}
