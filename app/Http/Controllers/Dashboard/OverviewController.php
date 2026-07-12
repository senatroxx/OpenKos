<?php

namespace App\Http\Controllers\Dashboard;

use App\Business\Dashboard\OverviewStatsCalculator;
use App\Enums\LeaseStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseUnitHistory;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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

        $activeLeases = Lease::where('status', LeaseStatus::Active->value)
            ->whereHas('unit.property', fn (Builder $q) => $q->whereIn('id', $accessibleProperties));

        $invoiceScope = Invoice::whereHas('lease', fn (Builder $q) => $q
            ->where('status', LeaseStatus::Active->value)
            ->whereHas('unit.property', fn (Builder $q) => $q->whereIn('id', $accessibleProperties)));

        $overdueInvoices = (clone $invoiceScope)
            ->payable()
            ->whereDate('due_date', '<', now())
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total - amount_paid), 0) as amount')
            ->first();

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

        $attention = [
            'overdue_invoices' => [
                'count' => (int) ($overdueInvoices->count ?? 0),
                'amount' => (int) ($overdueInvoices->amount ?? 0),
            ],
            'due_today' => $dueTodayInvoices,
            'open_maintenance' => $openMaintenance,
            'leases_ending_soon' => $leasesEndingSoon,
        ];

        $recentActivity = AuditLog::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q
                ->where('actor_type', $request->user()->getMorphClass())
                ->where('actor_id', $request->user()->id),
            )
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'description' => $this->describeAudit($log),
                'created_at' => $log->created_at->toISOString(),
                'subject_type' => $log->auditable_type,
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

        $financeResult = $finance->computeFinance($activeLeases);

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
}
