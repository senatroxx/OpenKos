<?php

namespace App\Business\Dashboard;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RentStatsCalculator
{
    public function computeStats(Collection $leases, int $today, int $currentMonth, int $currentYear): array
    {
        $leaseIds = $leases->pluck('id')->all();

        $paidLeaseIds = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereBetween('period_start', [
                Carbon::create($currentYear, $currentMonth, 1)->startOfDay(),
                Carbon::create($currentYear, $currentMonth, 1)->endOfMonth()->endOfDay(),
            ])
            ->whereIn('lease_id', $leaseIds)
            ->distinct()
            ->pluck('lease_id')
            ->toArray();

        $paidSet = array_flip($paidLeaseIds);

        $overdueCount = 0;
        $overdueAmount = 0.0;
        $dueTodayCount = 0;
        $dueSoonCount = 0;
        $paidCount = 0;

        foreach ($leases as $lease) {
            if (isset($paidSet[$lease->id])) {
                $paidCount++;

                continue;
            }

            $dueDay = $lease->rent_due_day;

            if ($dueDay < $today) {
                $overdueCount++;
                $overdueAmount += (float) $lease->rent_amount;
            } elseif ($dueDay === $today) {
                $dueTodayCount++;
            } elseif ($dueDay <= $today + 7) {
                $dueSoonCount++;
            }
        }

        return [
            'overdue' => ['count' => $overdueCount, 'amount' => $overdueAmount],
            'due_today' => $dueTodayCount,
            'due_soon' => $dueSoonCount,
            'paid' => $paidCount,
        ];
    }

    public function computeBillingStats(Builder $invoiceQuery, CarbonInterface $now): array
    {
        $overdueInvoices = (clone $invoiceQuery)
            ->payable()
            ->where('due_date', '<', $now->toDateString())
            ->get();

        $dueToday = (clone $invoiceQuery)
            ->payable()
            ->whereDate('due_date', '=', $now->toDateString())
            ->count();

        $dueSoon = (clone $invoiceQuery)
            ->payable()
            ->whereBetween('due_date', [
                $now->copy()->addDay()->toDateString(),
                $now->copy()->addDays(7)->toDateString(),
            ])
            ->count();

        $outstandingBalance = (float) (clone $invoiceQuery)
            ->payable()
            ->sum(DB::raw('total - amount_paid'));

        return [
            'overdue' => [
                'count' => $overdueInvoices->count(),
                'amount' => (int) $overdueInvoices->sum(fn (Invoice $i) => (float) $i->total - (float) $i->amount_paid),
            ],
            'due_today' => $dueToday,
            'due_soon' => $dueSoon,
            'outstanding_balance' => (int) $outstandingBalance,
        ];
    }

    public function transformEntry(Lease $lease, int $today): ?array
    {
        $hasPayment = $lease->has_payment_this_month ?? false;

        if ($hasPayment) {
            $status = 'paid';
            $daysOverdue = null;
        } else {
            $dueDay = $lease->rent_due_day;

            if ($dueDay < $today) {
                $status = 'overdue';
                $dueDateThisMonth = now()->setDay(min($dueDay, now()->daysInMonth));
                $daysOverdue = (int) $dueDateThisMonth->diffInDays(now(), false);
            } elseif ($dueDay === $today) {
                $status = 'due_today';
                $daysOverdue = null;
            } elseif ($dueDay <= $today + 7) {
                $status = 'due_soon';
                $daysOverdue = null;
            } else {
                return null;
            }
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
            'rent_amount' => (string) $lease->rent_amount,
            'rent_status' => $status,
        ];
    }
}
