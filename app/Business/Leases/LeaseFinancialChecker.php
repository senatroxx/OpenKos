<?php

namespace App\Business\Leases;

use Brick\Math\BigDecimal;

class LeaseFinancialChecker
{
    /**
     * @param  iterable<int, string>  $outstandingAmounts
     */
    public function outstandingBalance(iterable $outstandingAmounts): string
    {
        $total = BigDecimal::zero();

        foreach ($outstandingAmounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total->toString();
    }

    /**
     * @param  iterable<int, string>  $outstandingAmounts
     * @return array{balance: string, hasOutstanding: bool}
     */
    public function outstandingCheck(iterable $outstandingAmounts): array
    {
        $balance = $this->outstandingBalance($outstandingAmounts);

        return [
            'balance' => $balance,
            'hasOutstanding' => $balance > 0,
        ];
    }
}
