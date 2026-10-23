@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Bank statement imports</h1>
                <p class="text-slate-600">Upload a statement and reconcile payments to the correct unit.</p>
            </div>
            <a href="{{ route('bank-statements.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Upload statement</a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">File</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Rows</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($imports as $import)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $import->statement_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $import->file_name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $import->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $import->rows_count ?? $import->rows()->count() }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('bank-statements.reconciliation.review', $import) }}" class="text-blue-600 hover:text-blue-700">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No bank statements uploaded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $imports->links() }}
        </div>
    </div>
@endsection
