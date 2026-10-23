@extends('layouts.app')

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900 mb-2">Upload bank statement</h1>
            <p class="mb-6 text-sm text-slate-600">Upload an Excel or CSV statement and let the system match entries to tenant units.</p>

            <form action="{{ route('bank-statements.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label for="statement_date" class="mb-1 block text-sm font-medium text-slate-700">Statement date</label>
                    <input id="statement_date" type="date" name="statement_date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none" />
                </div>

                <div>
                    <label for="file" class="mb-1 block text-sm font-medium text-slate-700">Statement file</label>
                    <input id="file" type="file" name="file" accept=".xlsx,.xls,.csv" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none" />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Upload and reconcile
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
