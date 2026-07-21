<?php

namespace App\Http\Controllers\TenantPortal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Tenant;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);

        $table = Table::make()
            ->columns([
                Column::make('reference', 'Reference')->sortable()->searchable(),
                Column::make('start_date', 'Start')->sortable(),
                Column::make('end_date', 'End')->sortable(),
                Column::make('rent_amount', 'Rent')->sortable(),
                Column::make('status', 'Status')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'renewed', 'terminated', 'expired'])
                    ->query(fn (Builder $query, string $value) => $query->where('leases.status', $value)),
            ])
            ->defaultSort('-start_date');

        $result = $table->paginate(
            $tenant->leases()->with(['unit.property']),
            $request,
            'leases',
        );

        return Inertia::render('tenant-portal/lease/index', [
            ...$result,
        ]);
    }

    public function show(Request $request, Lease $lease): Response
    {
        $tenant = $this->tenant($request);
        $lease = $this->tenantLease($tenant, $lease);

        $lease->load('unit.property');

        return Inertia::render('tenant-portal/lease/show', [
            'lease' => $lease,
        ]);
    }

    public function invoices(Request $request, Lease $lease): Response
    {
        $tenant = $this->tenant($request);
        $lease = $this->tenantLease($tenant, $lease);

        $table = Table::make()
            ->columns([
                Column::make('reference', 'Reference')->searchable(),
                Column::make('period_start', 'Period')->sortable(),
                Column::make('due_date', 'Due date')->sortable(),
                Column::make('total', 'Total')->sortable(),
                Column::make('amount_paid', 'Paid')->sortable(),
                Column::make('outstanding', 'Outstanding'),
                Column::make('status', 'Status')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['pending', 'partial', 'paid', 'cancelled', 'void'])
                    ->query(fn (Builder $query, string $value) => $query->where('status', $value)),
            ])
            ->defaultSort('-period_start');

        $result = $table->paginate(
            $lease->invoices()->select('invoices.*')->selectRaw('(COALESCE(total, 0) - COALESCE(amount_paid, 0)) as outstanding'),
            $request,
            'invoices',
        );

        $result['invoices']->getCollection()->each->append('display_status');

        return Inertia::render('tenant-portal/lease/invoices', [
            ...$result,
            'lease' => $lease->load('unit.property'),
        ]);
    }

    public function history(Request $request, Lease $lease): Response
    {
        $tenant = $this->tenant($request);
        $lease = $this->tenantLease($tenant, $lease);

        $table = Table::make()
            ->columns([
                Column::make('effective_date', 'Date')->sortable(),
                Column::make('reason', 'Reason')->sortable()->searchable(),
            ])
            ->defaultSort('-effective_date');

        $result = $table->paginate(
            $lease->unitHistories()->with(['fromUnit:id,name', 'toUnit:id,name']),
            $request,
            'history',
        );

        return Inertia::render('tenant-portal/lease/history', [
            ...$result,
            'lease' => $lease->load('unit.property'),
        ]);
    }

    public function invoice(Request $request, Lease $lease, Invoice $invoice): Response
    {
        $tenant = $this->tenant($request);
        $lease = $this->tenantLease($tenant, $lease);

        abort_if($invoice->lease_id !== $lease->id, 404);

        $invoice->load(['lineItems', 'payments']);
        $invoice->append(['outstanding', 'display_status']);

        return Inertia::render('tenant-portal/lease/invoice', [
            'lease' => $lease->load('unit.property'),
            'invoice' => $invoice,
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
