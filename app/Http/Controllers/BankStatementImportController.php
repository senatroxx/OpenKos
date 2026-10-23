<?php

namespace App\Http\Controllers;

use App\Models\BankStatementImport;
use App\Models\BankStatementRow;
use App\Services\BankStatement\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BankStatementImportController extends Controller
{
    public function index(): View
    {
        $imports = BankStatementImport::latest()->paginate(20);

        return view('bank-statements.index', compact('imports'));
    }

    public function create(): View
    {
        return view('bank-statements.create');;
    }

    public function store(Request $request, ReconciliationService $reconciliationService): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'statement_date' => ['nullable', 'date'],
        ]);

        $file = $request->file('file');
        $path = $file->store('bank-statements');

        $import = BankStatementImport::create([
            'uploaded_by' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'statement_date' => $request->input('statement_date'),
            'status' => 'uploaded',
        ]);

        $rows = $this->readRows(Storage::path($path));

        foreach ($rows as $index => $row) {
            $record = BankStatementRow::create([
                'bank_statement_import_id' => $import->id,
                'row_number' => $index + 1,
                'transaction_date' => $row['transaction_date'] ?? null,
                'description' => $row['description'] ?? null,
                'unit_number' => $row['unit_number'] ?? null,
                'payer_name' => $row['payer_name'] ?? null,
                'amount' => (float) ($row['amount'] ?? 0),
                'currency' => $row['currency'] ?? 'USD',
                'match_status' => 'pending',
            ]);

            $reconciliationService->reconcileRow($record);
        }

        $import->update(['status' => 'processed']);

        return redirect()->route('bank-statements.index')->with('success', 'Bank statement imported and reconciled successfully.');
    }

    protected function readRows(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            $handle = fopen($path, 'r');
            $header = fgetcsv($handle);
            $rows = [];

            while (($data = fgetcsv($handle)) !== false) {
                $item = array_combine($header, $data);
                if (! is_array($item)) {
                    continue;
                }

                $rows[] = [
                    'transaction_date' => $item['date'] ?? $item['transaction_date'] ?? $item['posted_at'] ?? null,
                    'description' => $item['description'] ?? $item['narration'] ?? $item['details'] ?? null,
                    'unit_number' => $item['unit_number'] ?? $item['unit'] ?? $item['account_number'] ?? $item['reference'] ?? null,
                    'payer_name' => $item['payer_name'] ?? $item['customer_name'] ?? $item['name'] ?? null,
                    'amount' => $item['amount'] ?? $item['credit'] ?? $item['debit'] ?? 0,
                    'currency' => $item['currency'] ?? 'USD',
                ];
            }

            fclose($handle);

            return $rows;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);

        return array_values(array_filter(array_map(function ($line) {
            if (trim($line) === '') {
                return null;
            }

            $parts = preg_split('/\s{2,}|,/', trim($line));

            if (! is_array($parts) || count($parts) < 4) {
                return null;
            }

            return [
                'transaction_date' => $parts[0] ?? null,
                'description' => $parts[1] ?? null,
                'unit_number' => $parts[2] ?? null,
                'payer_name' => $parts[3] ?? null,
                'amount' => $parts[4] ?? 0,
                'currency' => 'USD',
            ];
        }, $lines)));
    }
}
