<?php

namespace App\Http\Controllers;

use App\Exports\PaymentReconciliationExport;
use App\Models\BankStatementImport;
use App\Models\BankStatementRow;
use App\Services\BankStatement\MonthlyUnitSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class BankStatementReconciliationController extends Controller
{
    public function index(): View
    {
        $imports = BankStatementImport::withCount('rows')->latest()->paginate(20);

        return view('bank-statements.reconciliation-index', compact('imports'));
    }

    public function review(BankStatementImport $import): View
    {
        $rows = $import->rows()->with(['unit', 'tenant'])->latest()->paginate(25);
        $summary = $rows->groupBy('match_status')->map(fn ($items) => $items->count());

        return view('bank-statements.reconciliation-review', compact('import', 'rows', 'summary'));
    }

    public function approve(BankStatementImport $import, Request $request): RedirectResponse
    {
        $request->validate([
            'rows' => ['array'],
            'rows.*' => ['integer', 'exists:bank_statement_rows,id'],
        ]);

        $ids = $request->input('rows', []);

        BankStatementRow::whereIn('id', $ids)->update([
            'match_status' => 'approved',
            'notes' => 'Approved by admin after reconciliation review.',
        ]);

        $import->update(['status' => 'reviewed']);

        return redirect()->route('bank-statements.reconciliation.review', $import)
            ->with('success', 'Selected bank statement rows were approved.');
    }

    public function export(BankStatementImport $import): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $rows = $import->rows()->with(['unit', 'tenant'])->get()->map(function (BankStatementRow $row) {
            $unit = $row->unit;
            $tenant = $row->tenant;

            $summary = app(MonthlyUnitSummaryService::class)->getSummaryForUnit($unit, now()->format('Y-m'));

            return [
                'month' => now()->format('Y-m'),
                'unit' => $unit?->name ?? $row->unit_number,
                'tenant' => $tenant?->name ?? $row->payer_name,
                'expected_rent' => $summary['monthly_rent'],
                'water_bill' => $summary['water_bill'],
                'waste_bill' => $summary['waste_bill'],
                'total_expected' => $summary['total_dues'],
                'amount_paid' => $summary['rent_paid'],
                'amount_matched' => (float) $row->amount,
                'balance' => $summary['balance'],
                'status' => $summary['status'],
            ];
        })->all();

        return Excel::download(new PaymentReconciliationExport($rows), 'reconciliation-'.$import->id.'-'.now()->format('Ymd').'.xlsx');
    }
}
