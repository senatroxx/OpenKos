<?php

namespace App\Services;

use App\Contracts\WhatsAppDriver;

class WhatsAppManager
{
    public function __construct(private WhatsAppDriver $driver) {}

    public function send(string $phone, string $message): void
    {
        $this->driver->send($phone, $message);
    }
}
