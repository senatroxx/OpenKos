<?php

namespace App\Exports;

use App\Models\PaymentReconciliation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PaymentReconciliationExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected readonly array $rows = []
    ) {}

    public function collection()
    {
        return collect($this->rows)->map(fn (array $row) => [
            'month' => $row['month'] ?? null,
            'unit' => $row['unit'] ?? null,
            'tenant' => $row['tenant'] ?? null,
            'expected_rent' => $row['expected_rent'] ?? 0,
            'water_bill' => $row['water_bill'] ?? 0,
            'waste_bill' => $row['waste_bill'] ?? 0,
            'total_expected' => $row['total_expected'] ?? 0,
            'amount_paid' => $row['amount_paid'] ?? 0,
            'amount_matched' => $row['amount_matched'] ?? 0,
            'balance' => $row['balance'] ?? 0,
            'status' => $row['status'] ?? null,
        ]);
    }

    public function headings(): array
    {
        return [
            'Month',
            'Unit',
            'Tenant',
            'Expected Rent',
            'Water Bill',
            'Waste Bill',
            'Total Expected',
            'Amount Paid',
            'Amount Matched',
            'Balance',
            'Status',
        ];
    }
}
