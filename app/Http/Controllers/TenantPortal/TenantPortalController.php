<?php

namespace App\Http\Controllers\TenantPortal;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class TenantPortalController extends Controller
{
    protected function tenant(Request $request): Tenant
    {
        $tenant = $request->user()->tenant()->first();

        abort_unless($tenant, 403);

        return $tenant;
    }

    /**
     * @return array{selectedLease: Lease|null, leases: Collection<int, Lease>}
     */
    protected function leaseContext(Request $request, Tenant $tenant): array
    {
        $leases = $tenant->leases()
            ->with('unit.property')
            ->orderByDesc('status')
            ->latest('start_date')
            ->get();
        $leaseId = $request->integer('lease');

        $selectedLease = $leaseId
            ? $leases->firstWhere('id', $leaseId)
            : $leases->firstWhere('status.value', 'active') ?? $leases->first();

        if ($leaseId) {
            abort_unless($selectedLease, 404);
        }

        return [
            'selectedLease' => $selectedLease,
            'leases' => $leases,
        ];
    }

    /**
     * @param  Collection<int, Lease>  $leases
     * @return array{selected: array<string, mixed>|null, leases: array<int, array<string, mixed>>}
     */
    protected function leaseContextPayload(?Lease $selectedLease, Collection $leases): array
    {
        return [
            'selected' => $selectedLease ? $this->leasePayload($selectedLease) : null,
            'leases' => $leases->map(fn (Lease $lease) => $this->leasePayload($lease))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function leasePayload(Lease $lease): array
    {
        return [
            'id' => $lease->id,
            'start_date' => $lease->start_date->toDateString(),
            'end_date' => $lease->end_date?->toDateString(),
            'status' => $lease->status->value,
            'unit_name' => $lease->unit?->name,
            'property_name' => $lease->unit?->property?->name,
        ];
    }
}
