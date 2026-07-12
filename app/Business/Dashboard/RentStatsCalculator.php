<?php

namespace App\Business\Dashboard;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RentStatsCalculator
{
    public function computeStats(Collection $leases, int $currentMonth, int $currentYear): array
    {
        $leaseIds = $leases->pluck('id')->all();

        $now = now()->startOfDay();
        $monthStart = Carbon::create($currentYear, $currentMonth, 1)->startOfDay();
        $monthEnd = Carbon::create($currentYear, $currentMonth, 1)->endOfMonth()->endOfDay();

        $overdueInvoices = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->payable()
            ->where('due_date', '<', $now)
            ->get();

        $dueTodayCount = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->payable()
            ->whereDate('due_date', '=', $now)
            ->count();

        $dueSoonCount = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->payable()
            ->whereBetween('due_date', [$now->copy()->addDay(), $now->copy()->addDays(7)])
            ->count();

        $paidLeaseIds = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->where('status', InvoiceStatus::Paid)
            ->whereBetween('period_start', [$monthStart, $monthEnd])
            ->distinct()
            ->pluck('lease_id');

        return [
            'overdue' => [
                'count' => $overdueInvoices->count(),
                'amount' => $overdueInvoices->sum(fn (Invoice $inv) => (float) $inv->total - (float) $inv->amount_paid),
            ],
            'due_today' => $dueTodayCount,
            'due_soon' => $dueSoonCount,
            'paid' => $paidLeaseIds->count(),
        ];
    }

    public function transformEntry(Lease $lease): ?array
    {
        $hasPayment = $lease->has_payment_this_month ?? false;

        if ($hasPayment) {
            $status = 'paid';
            $daysOverdue = null;
            $amount = 0;
        } else {
            $dueDate = $lease->next_due_date ? Carbon::parse($lease->next_due_date)->startOfDay() : null;

            if (! $dueDate) {
                return null;
            }

            $now = now()->startOfDay();

            if ($dueDate->lt($now)) {
                $status = 'overdue';
                $daysOverdue = (int) $dueDate->diffInDays($now, false);
            } elseif ($dueDate->eq($now)) {
                $status = 'due_today';
                $daysOverdue = null;
            } elseif ($dueDate->lte($now->copy()->addDays(7))) {
                $status = 'due_soon';
                $daysOverdue = null;
            } else {
                return null;
            }

            $amount = (float) ($lease->next_outstanding ?? 0);
        }

        $primaryTenant = $lease->primaryTenant;
        $tenants = $lease->tenants;
        $unit = $lease->unit;

        return [
            'id' => $lease->id,
            'tenant_name' => $tenants->pluck('name')->join(', ') ?: ($primaryTenant?->name ?? '—'),
            'unit_name' => $unit?->name ?? '—',
            'property_name' => $unit?->property?->name ?? '—',
            'rent_due_day' => $lease->rent_due_day,
            'days_overdue' => $daysOverdue,
            'rent_amount' => (string) $amount,
            'rent_status' => $status,
        ];
    }
}
