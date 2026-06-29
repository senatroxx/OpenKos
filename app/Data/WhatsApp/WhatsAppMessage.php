<?php

namespace App\Data\WhatsApp;

class WhatsAppMessage
{
    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly ?string $sender = null,
    ) {}
}
