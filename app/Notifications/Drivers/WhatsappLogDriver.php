<?php

namespace App\Notifications\Drivers;

use App\Contracts\WhatsAppDriver;
use Illuminate\Support\Facades\Log;

class WhatsappLogDriver implements WhatsAppDriver
{
    public function send(string $phone, string $message): void
    {
        Log::channel('reminders')->info('[WhatsApp] To: '.$phone.' — '.$message);
    }
}
