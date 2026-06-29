<?php

namespace App\Notifications\Drivers;

use App\Contracts\WhatsAppDriver;
use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;

class ApiCoIdDriver implements WhatsAppDriver
{
    public function __construct(private array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        throw new \RuntimeException('ApiCo.id driver not implemented.');
    }

    public function health(): DriverHealthResult
    {
        return new DriverHealthResult(false, 'ApiCo.id driver not implemented');
    }

    public function supportsPairing(): bool
    {
        return false;
    }

    public function configurationSchema(): array
    {
        return [
            'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true],
            'sender_name' => ['label' => 'Sender Name', 'type' => 'text', 'required' => false],
        ];
    }

    public function getPairingQrCode(): ?string
    {
        return null;
    }
}
