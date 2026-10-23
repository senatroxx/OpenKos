@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Reconciliation review</h1>
                <p class="text-slate-600">Review bank statement rows matched to units and approve the final report.</p>
            </div>
            <a href="{{ route('bank-statements.reconciliation.export', $import) }}" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Export Excel</a>
        </div>

        <div class="mb-6 grid gap-4 md:grid-cols-4">
            @foreach (['matched' => 'Matched', 'matched_by_name' => 'Matched by Name', 'approved' => 'Approved', 'unmatched' => 'Unmatched'] as $status => $label)
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-sm text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-bold text-slate-900">{{ $summary[$status] ?? 0 }}</div>
                </div>
            @endforeach
        </div>

        <form action="{{ route('bank-statements.reconciliation.approve', $import) }}" method="POST">
            @csrf
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600"><input type="checkbox" id="select-all" /></th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Unit</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Payer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Match</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3"><input type="checkbox" name="rows[]" value="{{ $row->id }}" class="row-checkbox" /></td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $row->transaction_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $row->unit?->name ?? $row->unit_number ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $row->payer_name ?? $row->tenant?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ number_format((float) $row->amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $row->match_status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">No rows available in this import.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Approve selected rows</button>
            </div>
        </form>

        <div class="mt-6">
            {{ $rows->links() }}
        </div>
    </div>

    <script>
        document.getElementById('select-all')?.addEventListener('change', function () {
            document.querySelectorAll('.row-checkbox').forEach(function (checkbox) {
                checkbox.checked = this.checked;
            }, this);
        });
    </script>
@endsection
