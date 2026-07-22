<?php

namespace App\Http\Controllers\TenantPortal;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);

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
        ]);
    }

    public function show(Request $request, Lease $lease): Response
    {
        $tenant = $this->tenant($request);
        $lease = $this->tenantLease($tenant, $lease);

        $lease->load([
            'unit.property',
            'unitHistories.fromUnit:id,name',
            'unitHistories.toUnit:id,name',
        ]);

        return Inertia::render('tenant-portal/lease/show', [
            'lease' => $lease,
        ]);
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()->tenant()->first();

        abort_unless($tenant, 403);

        return $tenant;
    }

    private function tenantLease(Tenant $tenant, Lease $lease): Lease
    {
        return $tenant->leases()
            ->whereKey($lease)
            ->firstOrFail();
    }
}
