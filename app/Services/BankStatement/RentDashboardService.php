<?php

namespace App\Services\BankStatement;

use App\Models\PaymentReconciliation;
use App\Models\Property;
use App\Models\Unit;
use Carbon\Carbon;

class RentDashboardService
{
    public function summarizeProperty(Property $property, ?string $month = null): array
    {
        $monthDate = $month ? Carbon::parse($month)->startOfMonth() : Carbon::now()->startOfMonth();

        $units = $property->units()->with(['leases' => fn ($query) => $query->active()->with('tenant')])->get();

        $rows = [];
        $totalExpected = 0;
        $totalPaid = 0;
        $totalBalance = 0;
        $paidUnits = 0;
        $partialUnits = 0;
        $arrearsUnits = 0;

        foreach ($units as $unit) {
            $unitSummary = $this->summarizeUnit($unit, $monthDate);
            $rows[] = [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'tenant_name' => $unitSummary['tenant_name'],
                'monthly_rent' => $unitSummary['monthly_rent'],
                'water_bill' => $unitSummary['water_bill'],
                'waste_bill' => $unitSummary['waste_bill'],
                'total_dues' => $unitSummary['total_dues'],
                'rent_paid' => $unitSummary['rent_paid'],
                'water_paid' => $unitSummary['water_paid'],
                'total_paid' => $unitSummary['total_paid'],
                'arrears' => $unitSummary['arrears'],
                'balance' => $unitSummary['balance'],
                'refundable_deposit' => $unitSummary['refundable_deposit'],
                'status' => $unitSummary['status'],
                'month' => $monthDate->format('Y-m'),
            ];

            $totalExpected += $unitSummary['total_dues'];
            $totalPaid += $unitSummary['total_paid'];
            $totalBalance += $unitSummary['balance'];

            match ($unitSummary['status']) {
                'cleared' => $paidUnits++,
                'partial' => $partialUnits++,
                default => $arrearsUnits++,
            };
        }

        return [
            'property_id' => $property->id,
            'property_name' => $property->name,
            'month' => $monthDate->format('Y-m'),
            'overview' => [
                'total_units' => $units->count(),
                'paid_units' => $paidUnits,
                'partial_units' => $partialUnits,
                'arrears_units' => $arrearsUnits,
                'total_expected' => $totalExpected,
                'total_paid' => $totalPaid,
                'total_balance' => $totalBalance,
            ],
            'rows' => $rows,
        ];
    }

    public function summarizeUnit(Unit $unit, Carbon $monthDate): array
    {
        $lease = $unit->leases()->active()->with('tenant')->first();

        $monthlyRent = (float) ($lease?->rent_amount ?? 0);
        $waterBill = (float) ($lease?->water_bill ?? 0);
        $wasteBill = (float) ($lease?->waste_bill ?? 0);
        $totalDues = $monthlyRent + $waterBill + $wasteBill;

        $paid = PaymentReconciliation::query()
            ->where('unit_id', $unit->id)
            ->whereMonth('month', $monthDate->month)
            ->whereYear('month', $monthDate->year)
            ->sum('amount_paid');

        $totalPaid = (float) $paid;
        $waterPaid = 0.0;
        $balance = $totalDues - $totalPaid;

        $status = 'cleared';
        if ($totalPaid > 0 && $balance > 0) {
            $status = 'partial';
        } elseif ($balance > 0) {
            $status = 'arrears';
        }

        return [
            'tenant_name' => $lease?->tenant?->name ?? 'No tenant assigned',
            'monthly_rent' => $monthlyRent,
            'water_bill' => $waterBill,
            'waste_bill' => $wasteBill,
            'total_dues' => $totalDues,
            'rent_paid' => $totalPaid,
            'water_paid' => $waterPaid,
            'total_paid' => $totalPaid,
            'arrears' => max(0, $balance),
            'balance' => $balance,
            'refundable_deposit' => (float) ($lease?->deposit_amount ?? 0),
            'status' => $status,
        ];
    }
}
