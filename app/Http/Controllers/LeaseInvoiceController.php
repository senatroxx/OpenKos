<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Lease;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaseInvoiceController extends Controller
{
    public function index(Request $request, Lease $lease): Response
    {
        $this->authorize('view', $lease);

        $table = Table::make()
            ->columns([
                Column::make('reference', 'Reference')->searchable(fn (Builder $q, string $search) => $q->where('reference', 'like', "%{$search}%")),
                Column::make('period_start', 'Period')->sortable(),
                Column::make('due_date', 'Due date')->sortable(),
                Column::make('total', 'Total')->sortable(),
                Column::make('amount_paid', 'Paid')->sortable(),
                Column::make('outstanding', 'Outstanding'),
                Column::make('status', 'Status')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['pending', 'partial', 'paid', 'cancelled', 'void'])
                    ->query(fn (Builder $q, string $value) => $q->where('status', $value)),
            ])
            ->defaultSort('-period_start');

        $result = $table->paginate(
            $lease->invoices()->select('invoices.*')->selectRaw('(COALESCE(total, 0) - COALESCE(amount_paid, 0)) as outstanding'),
            $request,
            'invoices',
        );

        $result['invoices']->getCollection()->each->append('display_status');

        return Inertia::render('leases/invoices', [
            ...$result,
            'lease' => $lease->only('id', 'reference', 'status'),
        ]);
    }

    public function show(Lease $lease, Invoice $invoice): Response
    {
        abort_if($invoice->lease_id !== $lease->id, 404);

        $this->authorize('view', $lease);

        $invoice->load(['lineItems', 'payments']);
        $invoice->append(['outstanding', 'display_status']);

        return Inertia::render('leases/invoice-detail', [
            'lease' => $lease->only('id', 'reference', 'status'),
            'invoice' => $invoice,
        ]);
    }
}
