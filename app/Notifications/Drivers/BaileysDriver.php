<?php

namespace App\Notifications\Drivers;

use App\Contracts\WhatsAppDriver;
use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;

class BaileysDriver implements WhatsAppDriver
{
    public function __construct(private array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        throw new \RuntimeException('Baileys driver not implemented.');
    }

    public function health(): DriverHealthResult
    {
        return new DriverHealthResult(false, 'Baileys driver not implemented');
    }

    public function supportsPairing(): bool
    {
        return true;
    }

    public function configurationSchema(): array
    {
        return [
            'url' => ['label' => 'WebSocket URL', 'type' => 'url', 'required' => true, 'placeholder' => 'ws://localhost:3000'],
            'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => false],
        ];
    }

    public function getPairingQrCode(): ?string
    {
        return null;
    }
}
