<?php

namespace App\Data\Dashboard;

use Illuminate\Support\Collection;

final readonly class FinancialDashboardData
{
    public function __construct(
        /** @var Collection<int, object> */
        public Collection $invoiceRows,
        /** @var Collection<int, object> */
        public Collection $expenseRows,
        /** @var Collection<int, object> */
        public Collection $paymentRows,
        /** @var Collection<int, object> */
        public Collection $allocationRows,
        /** @var Collection<int, object> */
        public Collection $upcomingRows,
        /** @var array<int, array{id: int, name: string, total_units: int, occupied_units: int, occupancy_percentage: int}> */
        public array $occupancyProperties,
    ) {}
}
