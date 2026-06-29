<?php

namespace App\Notifications\Drivers;

use App\Contracts\WhatsAppDriver;
use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;

class FonnteDriver implements WhatsAppDriver
{
    public function __construct(private array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        throw new \RuntimeException('Fonnte driver not implemented.');
    }

    public function health(): DriverHealthResult
    {
        return new DriverHealthResult(false, 'Fonnte driver not implemented');
    }

    public function supportsPairing(): bool
    {
        return false;
    }

    public function configurationSchema(): array
    {
        return [
            'token' => ['label' => 'API Token', 'type' => 'password', 'required' => true],
        ];
    }
}
