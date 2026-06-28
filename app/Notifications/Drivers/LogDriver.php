<?php

namespace App\Notifications\Drivers;

use App\Contracts\WhatsAppDriver;
use Illuminate\Support\Facades\Log;

class LogDriver implements WhatsAppDriver
{
    public function send(string $phone, string $message): void
    {
        Log::channel('whatsapp')->info('[WhatsApp] To: '.$phone.' — '.$message);
    }
}
