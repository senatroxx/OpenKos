<?php

namespace App\Contracts;

interface WhatsAppDriver
{
    public function send(string $phone, string $message): void;
}
