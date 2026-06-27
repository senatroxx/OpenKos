<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class RentController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $accessibleQuery = function (Builder $q) use ($request): void {
            if (! $request->user()->isOwner()) {
                $q->whereHas('room.property.users', fn (Builder $q) => $q->whereKey($request->user()->id));
            }
        };

        $now = now();
        $today = (int) $now->day;
        $currentMonth = (int) $now->month;
        $currentYear = (int) $now->year;

        $baseQuery = Lease::query()
            ->where('status', 'active')
            ->where('start_date', '<=', $now);

        ($accessibleQuery)($baseQuery);

        $allActive = (clone $baseQuery)->get();

        $stats = $this->computeStats($allActive, $today, $currentMonth, $currentYear);

        $table = Table::make()
            ->columns([
                Column::make('tenant_name', 'Tenant')->searchable(function (Builder $q, string $search): void {
                    $q->whereHas('tenants', fn (Builder $q) => $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']))
                        ->orWhereHas('room', fn (Builder $q) => $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']))
                        ->orWhereHas('room.property', fn (Builder $q) => $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']));
                }),
                Column::make('room_name', 'Room'),
                Column::make('property_name', 'Property'),
                Column::make('rent_due_day', 'Due Date')->sortable(),
                Column::make('rent_amount', 'Amount Due')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', [
                    ['value' => 'overdue', 'label' => 'Overdue'],
                    ['value' => 'due_today', 'label' => 'Due Today'],
                    ['value' => 'due_soon', 'label' => 'Due Soon'],
                    ['value' => 'paid', 'label' => 'Paid'],
                ])
                    ->query(function (Builder $q, string $value) use ($today, $currentMonth, $currentYear): void {
                        $hasPayment = fn (Builder $q) => $q->whereHas('payments', fn (Builder $q) => $q
                            ->where('paymentable_type', Lease::class)
                            ->whereNotIn('status', ['cancelled'])
                            ->whereMonth('period_start', $currentMonth)
                            ->whereYear('period_start', $currentYear));

                        $noPayment = fn (Builder $q) => $q->whereDoesntHave('payments', fn (Builder $q) => $q
                            ->where('paymentable_type', Lease::class)
                            ->whereNotIn('status', ['cancelled'])
                            ->whereMonth('period_start', $currentMonth)
                            ->whereYear('period_start', $currentYear));

                        match ($value) {
                            'paid' => $hasPayment($q),
                            'overdue' => $noPayment($q)->where('rent_due_day', '<', $today),
                            'due_today' => $noPayment($q)->where('rent_due_day', '=', $today),
                            'due_soon' => $noPayment($q)->whereBetween('rent_due_day', [$today + 1, min($today + 7, 31)]),
                        };
                    }),
                Filter::select('properties', 'Property', function () use ($request): array {
                    return Property::query()
                        ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                            'users',
                            fn (Builder $q) => $q->whereKey($request->user()->id),
                        ))
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (Property $p) => ['value' => (string) $p->id, 'label' => $p->name])
                        ->all();
                })
                    ->query(fn (Builder $q, string $value) => $q->whereHas(
                        'room',
                        fn (Builder $q) => $q->whereIn('property_id', explode(',', $value)),
                    )),
            ])
            ->defaultSort('rent_due_day');

        $query = (clone $baseQuery)
            ->with(['primaryTenant:id,name,phone', 'tenants:id,name,phone', 'room:id,name,property_id', 'room.property:id,name'])
            ->addSelect(['has_payment_this_month' => Payment::query()
                ->selectRaw('COUNT(*) > 0')
                ->whereColumn('paymentable_id', 'leases.id')
                ->where('paymentable_type', Lease::class)
                ->whereNotIn('status', ['cancelled'])
                ->whereMonth('period_start', $currentMonth)
                ->whereYear('period_start', $currentYear),
            ]);

        $result = $table->paginate($query, $request, 'entries');

        $entries = collect($result['entries']->items())
            ->map(fn (Lease $lease) => $this->transformEntry($lease, $today))
            ->filter(fn (?array $entry) => $entry !== null)
            ->values();

        $result['entries'] = $result['entries']->setCollection($entries);

        return Inertia::render('dashboard/rent', [
            ...$result,
            'stats' => $stats,
        ]);
    }

    /**
     * @return array{overdue: array{count: int, amount: float}, due_today: int, due_soon: int, paid: int}
     */
    private function computeStats(Collection $leases, int $today, int $currentMonth, int $currentYear): array
    {
        $leaseIds = $leases->pluck('id')->all();

        $paidLeaseIds = Payment::query()
            ->where('paymentable_type', Lease::class)
            ->whereNotIn('status', ['cancelled'])
            ->whereMonth('period_start', $currentMonth)
            ->whereYear('period_start', $currentYear)
            ->whereIn('paymentable_id', $leaseIds)
            ->distinct()
            ->pluck('paymentable_id')
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

    private function transformEntry(Lease $lease, int $today): ?array
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
        $room = $lease->room;

        return [
            'id' => $lease->id,
            'tenant_name' => $tenants->pluck('name')->join(', ') ?: ($primaryTenant?->name ?? '—'),
            'room_name' => $room?->name ?? '—',
            'property_name' => $room?->property?->name ?? '—',
            'rent_due_day' => $lease->rent_due_day,
            'days_overdue' => $daysOverdue,
            'rent_amount' => (string) $lease->rent_amount,
            'rent_status' => $status,
        ];
    }
}
