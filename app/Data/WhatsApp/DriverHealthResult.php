<?php

namespace App\Data\WhatsApp;

class DriverHealthResult
{
    public function __construct(
        public readonly bool $healthy,
        public readonly ?string $message = null,
    ) {}
}
