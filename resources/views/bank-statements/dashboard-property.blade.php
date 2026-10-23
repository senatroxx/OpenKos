@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $property->name }} rent overview</h1>
                <p class="text-sm text-slate-600">Monthly status for {{ $month }}</p>
            </div>
            <form method="GET" class="flex items-center gap-3">
                <input type="month" name="month" value="{{ $month }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Refresh</button>
            </form>
        </div>

        <div class="mb-6 grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">Expected</div>
                <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($summary['overview']['total_expected'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">Paid</div>
                <div class="mt-2 text-3xl font-bold text-emerald-600">{{ number_format($summary['overview']['total_paid'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">Arrears</div>
                <div class="mt-2 text-3xl font-bold text-red-600">{{ number_format($summary['overview']['total_balance'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">Status</div>
                <div class="mt-2 text-xl font-bold text-slate-900">{{ $summary['overview']['arrears_units'] > 0 ? 'Monitoring' : 'Healthy' }}</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Tenant</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Rent</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Total Due</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Paid</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Balance</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($summary['rows'] as $row)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-800">{{ $row['unit_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $row['tenant_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ number_format($row['monthly_rent'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ number_format($row['total_dues'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-emerald-700">{{ number_format($row['total_paid'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ number_format($row['balance'], 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium
                                    @if ($row['status'] === 'cleared') bg-emerald-100 text-emerald-700
                                    @elseif ($row['status'] === 'partial') bg-amber-100 text-amber-700
                                    @else bg-red-100 text-red-700 @endif">
                                    {{ ucfirst($row['status']) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
