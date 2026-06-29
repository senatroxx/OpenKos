<?php

namespace App\Console\Commands;

use App\Actions\Reminders\SendRentReminders;
use App\Models\Lease;
use Illuminate\Console\Command;

class SendRentRemindersCommand extends Command
{
    protected $signature = 'rent:send-reminders {lease? : Optional lease ID for manual send}';

    protected $description = 'Send rent reminders via WhatsApp';

    public function handle(SendRentReminders $action): void
    {
        $leaseId = $this->argument('lease');
        $lease = $leaseId ? Lease::findOrFail($leaseId) : null;

        $count = $action->execute($lease)->count();

        $this->info("Sent {$count} reminder(s).");
    }
}
