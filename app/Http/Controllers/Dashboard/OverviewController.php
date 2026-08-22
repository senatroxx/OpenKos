<?php

namespace App\Http\Controllers\Dashboard;

use App\Business\Dashboard\OverviewStatsCalculator;
use App\Enums\LeaseStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseUnitHistory;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Unit;
use App\Support\DateTimeFormatter;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(Request $request, OverviewStatsCalculator $finance): Response
    {
        $properties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->withCount([
                'units',
                'units as occupied_units_count' => fn (Builder $q) => $q
                    ->where(function (Builder $q) {
                        $q->where('status', UnitStatus::Occupied)
                            ->orWhereHas('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value));
                    }),
                'units as maintenance_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Maintenance)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)),
                'units as unavailable_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Unavailable)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $totalUnits = $properties->sum('units_count');
        $occupiedUnits = $properties->sum('occupied_units_count');
        $maintenanceUnits = $properties->sum('maintenance_units_count');
        $unavailableUnits = $properties->sum('unavailable_units_count');

        $accessibleProperties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->pluck('id');

        $accessibleLeases = Lease::query()
            ->whereHas('unit.property', fn (Builder $q) => $q->whereIn('id', $accessibleProperties));

        $activeLeases = (clone $accessibleLeases)
            ->where('status', LeaseStatus::Active->value);

        $invoiceScope = Invoice::query()
            ->whereIn('lease_id', (clone $accessibleLeases)->select('id'));

        $overdueInvoices = (clone $invoiceScope)
            ->payable()
            ->whereDate('due_date', '<', now())
            ->get(['currency', 'total', 'amount_paid']);

        $dueTodayInvoices = (clone $invoiceScope)
            ->payable()
            ->whereDate('due_date', now())
            ->count();

        $openMaintenance = MaintenanceTicket::whereIn('property_id', $accessibleProperties)
            ->whereIn('status', [MaintenanceStatus::Reported->value, MaintenanceStatus::InProgress->value])
            ->count();

        $leasesEndingSoon = (clone $activeLeases)
            ->whereDate('end_date', '<=', Carbon::today()->addDays(30))
            ->whereDate('end_date', '>', Carbon::today())
            ->count();

        $pendingPaymentVerification = Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->whereHas('invoice.lease.unit.property', fn (Builder $q) => $q->whereIn('id', $accessibleProperties))
            ->count();

        $attention = [
            'overdue_invoices' => [
                'count' => $overdueInvoices->count(),
                'amounts' => $this->aggregateMoney($overdueInvoices, fn (Invoice $invoice): string => BigDecimal::of((string) $invoice->total)
                    ->minus((string) $invoice->amount_paid)
                    ->toString()),
            ],
            'due_today' => $dueTodayInvoices,
            'open_maintenance' => $openMaintenance,
            'leases_ending_soon' => $leasesEndingSoon,
            'pending_payment_verification' => $pendingPaymentVerification,
        ];

        $auditLogs = AuditLog::query()
            ->with(['actor'])
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q
                ->where('actor_type', $request->user()->getMorphClass())
                ->where('actor_id', $request->user()->id),
            )
            ->latest()
            ->take(10)
            ->get();

        $paymentIds = $auditLogs
            ->filter(fn (AuditLog $log) => $log->auditable_type && class_basename($log->auditable_type) === 'Payment' && $log->auditable_id)
            ->pluck('auditable_id')
            ->unique();

        $payments = $paymentIds->isNotEmpty()
            ? Payment::query()->with('invoice')->whereIn('id', $paymentIds)->get()->keyBy('id')
            : collect();

        $invoiceIds = $auditLogs
            ->filter(fn (AuditLog $log) => $log->auditable_type && class_basename($log->auditable_type) === 'Invoice' && $log->auditable_id)
            ->pluck('auditable_id')
            ->unique();

        $invoices = $invoiceIds->isNotEmpty()
            ? Invoice::query()->whereIn('id', $invoiceIds)->get()->keyBy('id')
            : collect();

        $recentActivity = $auditLogs
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'description' => $this->describeAudit($log),
                'created_at' => DateTimeFormatter::iso($log->created_at),
                'subject_type' => $log->auditable_type,
                'subject_id' => $log->auditable_id,
                'actor_name' => $log->actor?->name ?? 'System',
                'action_url' => $this->resolveActionUrl($log, $payments, $invoices),
            ])
            ->values()
            ->toArray();

        $ticketFormUnits = Unit::query()
            ->select(['id', 'slug', 'name', 'property_id', 'status'])
            ->withCount(['leases as active_lease_count' => fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)])
            ->with(['leases' => fn ($q) => $q->where('status', LeaseStatus::Active->value)->with('tenants:id,name')])
            ->addSelect([
                'has_maintenance_transfer' => LeaseUnitHistory::query()
                    ->selectRaw('1')
                    ->whereColumn('from_unit_id', 'units.id')
                    ->where('reason', 'maintenance')
                    ->limit(1),
            ])
            ->whereIn('property_id', $accessibleProperties)
            ->orderBy('name')
            ->get();

        $ticketFormProperties = Property::query()
            ->whereIn('id', $accessibleProperties)
            ->orderBy('name')
            ->get(['id', 'name']);

        $countryCode = Setting::get('country_code', 'ID');
        $regions = Region::where('country_code', $countryCode)
            ->orderBy('name')
            ->get(['id', 'name']);

        $propertyTypes = PropertyType::active()->ordered()->get(['slug', 'label']);

        $financeResult = $finance->computeFinance($accessibleLeases);

        return Inertia::render('dashboard/overview', [
            'attention' => $attention,
            'finance' => $financeResult,
            'stats' => [
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'available_units' => $totalUnits - $occupiedUnits - $maintenanceUnits - $unavailableUnits,
                'maintenance_units' => $maintenanceUnits,
                'unavailable_units' => $unavailableUnits,
                'occupancy_percentage' => $totalUnits > 0
                    ? round(($occupiedUnits / $totalUnits) * 100)
                    : 0,
                'properties' => $properties->map(fn (Property $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'total_units' => $p->units_count,
                    'occupied_units' => $p->occupied_units_count,
                    'available_units' => $p->units_count - $p->occupied_units_count - $p->maintenance_units_count - $p->unavailable_units_count,
                    'maintenance_units' => $p->maintenance_units_count,
                    'unavailable_units' => $p->unavailable_units_count,
                    'occupancy_percentage' => $p->units_count > 0
                        ? round(($p->occupied_units_count / $p->units_count) * 100)
                        : 0,
                ])->values()->toArray(),
            ],
            'recent_activity' => $recentActivity,
            'properties' => $ticketFormProperties,
            'units' => $ticketFormUnits,
            'regions' => $regions,
            'propertyTypes' => $propertyTypes,
        ]);
    }

    private function describeAudit(AuditLog $log): string
    {
        $model = $log->auditable_type ? class_basename($log->auditable_type) : null;
        $op = match ($log->operation) {
            'create' => 'created',
            'update' => 'updated',
            'delete' => 'deleted',
            default => $log->operation,
        };

        if ($model) {
            return ucfirst($model).' '.$op;
        }

        return ucfirst($log->operation);
    }

    private function resolveActionUrl(AuditLog $log, Collection $payments, Collection $invoices): ?string
    {
        if (! $log->auditable_type) {
            return null;
        }

        $baseName = class_basename($log->auditable_type);

        if ($baseName === 'Payment' && $log->auditable_id) {
            /** @var Payment|null $payment */
            $payment = $payments->get($log->auditable_id);
            $leaseId = $payment?->invoice?->lease_id;

            return $leaseId ? route('leases.workspace.payments', $leaseId) : route('dashboard.rent');
        }

        if ($baseName === 'Invoice' && $log->auditable_id) {
            /** @var Invoice|null $invoice */
            $invoice = $invoices->get($log->auditable_id);
            $leaseId = $invoice?->lease_id;
            if ($leaseId) {
                return route('leases.workspace.invoices.show', [
                    'lease' => $leaseId,
                    'invoice' => $invoice->id,
                ]);
            }

            return route('dashboard.rent');
        }

        return match ($baseName) {
            'Lease' => $log->auditable_id ? route('leases.show', $log->auditable_id) : route('leases.index'),
            'Tenant' => $log->auditable_id ? route('tenants.show', $log->auditable_id) : route('tenants.index'),
            'MaintenanceTicket' => route('maintenance-tickets.index'),
            'Property' => route('properties.index'),
            default => null,
        };
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array<int, array{currency: string, amount: string}>
     */
    private function aggregateMoney(Collection $invoices, callable $amount): array
    {
        return $invoices
            ->groupBy(fn (Invoice $invoice): string => $invoice->currency)
            ->map(function (Collection $invoices, string $currency) use ($amount): array {
                $total = $invoices->reduce(
                    fn (BigDecimal $total, Invoice $invoice): BigDecimal => $total->plus($amount($invoice)),
                    BigDecimal::zero(),
                );

                return ['currency' => $currency, 'amount' => $total->toString()];
            })
            ->values()
            ->all();
    }
}
