@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Rent dashboard</h1>
                <p class="text-sm text-slate-600">Track paid, partial, and arrears across all apartments.</p>
            </div>
            <form method="GET" class="flex items-center gap-3">
                <input type="month" name="month" value="{{ $month ?? now()->format('Y-m') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Filter</button>
            </form>
        </div>

        <div class="mb-6 grid gap-4 md:grid-cols-4">
            @php
                $totals = $propertySummaries->reduce(function ($carry, $item) {
                    $carry['paid'] += $item['overview']['paid_units'];
                    $carry['partial'] += $item['overview']['partial_units'];
                    $carry['arrears'] += $item['overview']['arrears_units'];
                    $carry['expected'] += $item['overview']['total_expected'];
                    $carry['balance'] += $item['overview']['total_balance'];
                    return $carry;
                }, ['paid' => 0, 'partial' => 0, 'arrears' => 0, 'expected' => 0, 'balance' => 0]);
            @endphp

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">Paid units</div>
                <div class="mt-2 text-3xl font-bold text-slate-900">{{ $totals['paid'] }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">Partial</div>
                <div class="mt-2 text-3xl font-bold text-amber-600">{{ $totals['partial'] }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">Arrears</div>
                <div class="mt-2 text-3xl font-bold text-red-600">{{ $totals['arrears'] }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">Balance due</div>
                <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($totals['balance'], 2) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Apartment</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Units</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Paid</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Partial</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Arrears</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Balance</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($propertySummaries as $summary)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $summary['property_name'] }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $summary['overview']['total_units'] }}</td>
                                <td class="px-4 py-3 text-sm text-emerald-700">{{ $summary['overview']['paid_units'] }}</td>
                                <td class="px-4 py-3 text-sm text-amber-700">{{ $summary['overview']['partial_units'] }}</td>
                                <td class="px-4 py-3 text-sm text-red-700">{{ $summary['overview']['arrears_units'] }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ number_format($summary['overview']['total_balance'], 2) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('bank-statements.dashboard.property', ['property' => $summary['property_id'], 'month' => $month]) }}" class="text-blue-600 hover:text-blue-700">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">No properties yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
