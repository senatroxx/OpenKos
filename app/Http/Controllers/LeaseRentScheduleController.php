<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Lease;
use Illuminate\Http\JsonResponse;

class LeaseRentScheduleController extends Controller
{
    public function __invoke(Lease $lease): JsonResponse
    {
        if (! request()->user()->hasRole(Role::Owner->value)) {
            $this->authorize('view', $lease);
        }

        $schedule = $lease->invoices()
            ->orderBy('period_start')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'period_start' => $invoice->period_start->toDateString(),
                'period_end' => $invoice->period_end->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'amount' => $invoice->total,
                'amount_paid' => $invoice->amount_paid,
                'outstanding' => $invoice->outstanding,
                'status' => match ($invoice->status) {
                    InvoiceStatus::Paid => 'paid',
                    InvoiceStatus::Partial => 'partial',
                    InvoiceStatus::Cancelled, InvoiceStatus::Void => 'cancelled',
                    InvoiceStatus::Pending => $invoice->isOverdue()
                        ? 'overdue'
                        : ($invoice->period_start->isFuture() ? 'upcoming' : 'due'),
                },
            ]);

        return response()->json([
            'schedule' => $schedule,
        ]);
    }
}
