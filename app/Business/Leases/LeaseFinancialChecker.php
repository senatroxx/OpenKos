<?php

namespace App\Business\Leases;

use App\Models\Lease;

class LeaseFinancialChecker
{
    /**
     * @return int Total outstanding amount in cents.
     */
    public function outstandingBalance(Lease $lease): int
    {
        return (int) $lease->invoices()->overdue()->get()->sum(fn ($invoice) => (float) $invoice->outstanding);
    }

    /**
     * @return array{balance: int, hasOutstanding: bool}
     */
    public function outstandingCheck(Lease $lease): array
    {
        $balance = $this->outstandingBalance($lease);

        return [
            'balance' => $balance,
            'hasOutstanding' => $balance > 0,
        ];
    }
}
