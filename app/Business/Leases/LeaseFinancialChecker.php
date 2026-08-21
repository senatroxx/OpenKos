<?php

namespace App\Business\Leases;

use App\Models\Lease;
use Brick\Math\BigDecimal;

class LeaseFinancialChecker
{
    public function outstandingBalance(Lease $lease): string
    {
        return $lease->invoices()->overdue()->get()
            ->reduce(
                fn (BigDecimal $total, $invoice): BigDecimal => $total->plus($invoice->outstanding),
                BigDecimal::zero(),
            )
            ->toString();
    }

    /**
     * @return array{balance: string, hasOutstanding: bool}
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
