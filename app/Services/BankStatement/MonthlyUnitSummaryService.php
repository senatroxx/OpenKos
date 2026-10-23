<?php

namespace App\Services\BankStatement;

use App\Models\Lease;
use App\Models\PaymentReconciliation;
use App\Models\Property;
use App\Models\Unit;
use Carbon\Carbon;

class MonthlyUnitSummaryService
{
    public function getSummaryForUnit(Unit $unit, ?string $month = null): array
    {
        $monthDate = $month ? Carbon::parse($month)->startOfMonth() : now()->startOfMonth();

        $lease = $unit->leases()->active()->first();

        $rent = $lease?->rent_amount ?? 0;
        $water = 0;
        $waste = 0;
        $totalExpected = (float) $rent + $water + $waste;

        $paid = PaymentReconciliation::query()
            ->where('unit_id', $unit->id)
            ->whereMonth('month', $monthDate->month)
            ->whereYear('month', $monthDate->year)
            ->sum('amount_paid');

        $balance = $totalExpected - $paid;

        return [
            'monthly_rent' => $rent,
            'water_bill' => $water,
            'waste_bill' => $waste,
            'total_dues' => $totalExpected,
            'rent_paid' => $paid,
            'water_paid' => 0,
            'total_paid' => $paid,
            'arrears' => max(0, $balance),
            'balance' => $balance,
            'refundable_deposit' => $lease?->deposit_amount ?? 0,
            'status' => $balance > 0 ? 'arrears' : 'cleared',
            'month' => $monthDate->format('Y-m'),
        ];
    }

    public function getSummaryForProperty(Property $property, ?string $month = null): array
    {
        $units = $property->units()->with('leases')->get();

        $summary = [
            'units_total' => $units->count(),
            'paid' => 0,
            'arrears' => 0,
            'cleared' => 0,
            'unmatched' => 0,
            'rows' => [],
        ];

        foreach ($units as $unit) {
            $unitSummary = $this->getSummaryForUnit($unit, $month);
            $summary['rows'][] = [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                ...$unitSummary,
            ];

            if ($unitSummary['status'] === 'arrears') {
                $summary['arrears']++;
            } else {
                $summary['cleared']++;
            }
        }

        return $summary;
    }
}
