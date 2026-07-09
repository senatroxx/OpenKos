<?php

namespace App\Console\Commands;

use App\Actions\Invoices\GenerateInvoices;
use App\Models\Lease;
use Illuminate\Console\Command;

class GenerateInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate {lease? : Optional lease ID for manual generation}';

    protected $description = 'Generate invoices for upcoming billing periods';

    public function handle(GenerateInvoices $action): void
    {
        $leaseId = $this->argument('lease');
        $lease = $leaseId ? Lease::findOrFail($leaseId) : null;

        $count = $action->execute($lease);

        $this->info("Generated {$count} invoice(s).");
    }
}
