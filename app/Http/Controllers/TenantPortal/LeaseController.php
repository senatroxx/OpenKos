<?php

namespace App\Http\Controllers\TenantPortal;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends TenantPortalController
{
    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $leaseContext = $this->leaseContext($request, $tenant);

        return Inertia::render('tenant-portal/lease/index', [
            'currentLeases' => $tenant->leases()
                ->active()
                ->with('unit.property')
                ->latest('start_date')
                ->get(),
            'previousLeases' => $tenant->leases()
                ->where('status', '!=', LeaseStatus::Active->value)
                ->with('unit.property')
                ->latest('start_date')
                ->get(),
            'leaseContext' => $this->leaseContextPayload(
                $leaseContext['selectedLease'],
                $leaseContext['leases'],
            ),
        ]);
    }

    public function show(Request $request, Lease $lease): Response
    {
        $tenant = $this->tenant($request);
        $leaseContext = $this->leaseContext($request, $tenant);
        $lease = $this->tenantLease($tenant, $lease);

        $lease->load([
            'unit.property',
            'unitHistories.fromUnit:id,name',
            'unitHistories.toUnit:id,name',
        ]);

        return Inertia::render('tenant-portal/lease/show', [
            'lease' => $lease,
            'leaseContext' => $this->leaseContextPayload($lease, $leaseContext['leases']),
        ]);
    }

    private function tenantLease(Tenant $tenant, Lease $lease): Lease
    {
        return $tenant->leases()
            ->whereKey($lease)
            ->firstOrFail();
    }
}
