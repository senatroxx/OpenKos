<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->reference ?? $invoice->getKey() }}</title>
    <style>
        @page { margin: 36px 42px; }
        * { box-sizing: border-box; }
        html, body { background: #ffffff; }
        body { color: #172033; font-family: "DejaVu Sans", sans-serif; font-size: 11px; line-height: 1.5; margin: 0; }
        h1, h2, p { margin: 0; }
        h1 { font-size: 25px; letter-spacing: -0.4px; }
        h2 { font-size: 12px; letter-spacing: 0.7px; text-transform: uppercase; }
        table { border-collapse: collapse; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .muted { color: #667085; }
        .right { text-align: right; }
        .header td { padding-bottom: 26px; vertical-align: top; }
        .brand { font-size: 16px; font-weight: 700; }
        .status { border: 1px solid #667085; border-radius: 10px; display: inline-block; font-size: 9px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 9px; text-transform: uppercase; }
        .meta { border: 1px solid #d8dee8; margin-bottom: 22px; }
        .meta td { padding: 10px 12px; vertical-align: top; width: 33.33%; }
        .meta td + td { border-left: 1px solid #d8dee8; }
        .label { color: #667085; font-size: 9px; letter-spacing: 0.5px; margin-bottom: 3px; text-transform: uppercase; }
        .value { font-weight: 600; }
        .context { margin-bottom: 22px; }
        .context td { padding: 2px 0; vertical-align: top; }
        .context .key { color: #667085; padding-right: 16px; width: 110px; }
        .bill-to { margin-bottom: 22px; }
        .bill-to td { padding-top: 4px; vertical-align: top; }
        .bill-to .name { font-size: 13px; font-weight: 700; }
        .bill-to .detail { color: #667085; }
        .items { margin-top: 8px; }
        .items th { background: #f5f7fa; border-bottom: 1px solid #d8dee8; color: #667085; font-size: 9px; letter-spacing: 0.5px; padding: 9px 10px; text-align: left; text-transform: uppercase; }
        .items th:last-child { text-align: right; }
        .items td { border-bottom: 1px solid #e8ecf2; padding: 10px; vertical-align: top; }
        .empty { color: #667085; text-align: center; }
        .totals { margin-left: auto; margin-top: 16px; width: 280px; }
        .totals td { padding: 5px 0 5px 12px; }
        .totals td:first-child { color: #667085; }
        .totals .outstanding td { border-top: 1px solid #172033; font-size: 14px; font-weight: 700; padding-top: 9px; }
        .payment-details { margin-top: 28px; }
        .payment-details h2 { margin-bottom: 8px; }
        .payment-details .single { border: 1px solid #d8dee8; padding: 10px 12px; }
        .payment-details .single td { padding: 2px 12px 2px 0; vertical-align: top; }
        .payment-details .single td:last-child { padding-right: 0; }
        .payments th { border-bottom: 1px solid #d8dee8; color: #667085; font-size: 9px; letter-spacing: 0.5px; padding: 7px 8px; text-align: left; text-transform: uppercase; }
        .payments th:last-child, .payments td:last-child { text-align: right; }
        .payments td { border-bottom: 1px solid #e8ecf2; padding: 8px; }
        .payment-total td { border-bottom: 0; font-weight: 700; padding-top: 9px; }
        .footer { border-top: 1px solid #d8dee8; color: #667085; font-size: 9px; margin-top: 30px; padding-top: 10px; }
    </style>
</head>
<body>
@php
    $formatDate = static fn ($date): string => $date?->copy()->locale($locale)->translatedFormat('d M Y') ?? '-';
    $formatDateTime = static fn ($date, string $format = 'd M Y, H:i'): string => App\Support\DateTimeFormatter::inDisplayTimezone($date)?->locale($locale)->translatedFormat($format) ?? '-';
    $formatMoney = static fn (string $amount): string => (string) Illuminate\Support\Number::currency(
        (float) $amount,
        in: $currency,
        locale: $locale,
        precision: app(App\Services\Payments\MoneyConverter::class)->scale($currency),
    );
    $property = $invoice->lease?->unit?->property;
    $propertyAddress = collect([
        $property?->address,
        $property?->city?->name,
        $property?->region?->name,
        $property?->postal_code,
    ])->filter()->implode(', ');
    $status = match ($invoice->display_status) {
        'partial' => 'Partially Paid',
        'cancelled' => 'Cancelled',
        'void' => 'Void',
        default => str($invoice->display_status)->replace('_', ' ')->title(),
    };
    $tenant = $invoice->lease?->primaryTenant;
    $payments = $invoice->payments ?? collect();
    $generatedAt = App\Support\DateTimeFormatter::inDisplayTimezone(now())->locale($locale)->translatedFormat('d M Y, H:i T');
@endphp

<table class="header">
    <tr>
        <td>
            <p class="brand">{{ $siteName }}</p>
            <p class="muted">{{ $property?->name ?? 'Property' }}</p>
        </td>
        <td class="right">
            <h1>Invoice</h1>
            <p>{{ $invoice->reference ?? '#'.$invoice->getKey() }}</p>
            <p style="margin-top: 7px"><span class="status">{{ $status }}</span></p>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td>
            <p class="label">Issue date</p>
            <p class="value">{{ $formatDateTime($invoice->created_at, 'd M Y') }}</p>
        </td>
        <td>
            <p class="label">Billing period</p>
            <p class="value">{{ $formatDate($invoice->period_start) }} - {{ $formatDate($invoice->period_end) }}</p>
        </td>
        <td>
            <p class="label">Due date</p>
            <p class="value">{{ $formatDate($invoice->due_date) }}</p>
        </td>
    </tr>
</table>

<table class="bill-to">
    <tr>
        <td>
            <h2>Bill To</h2>
            <p class="name">{{ $tenant?->name ?? '-' }}</p>
            @if ($tenant)
                <p class="detail">Tenant ID {{ $tenant->getKey() }}</p>
                @if ($tenant->user?->email)
                    <p class="detail">{{ $tenant->user->email }}</p>
                @endif
                @if ($tenant->phone)
                    <p class="detail">{{ $tenant->phone }}</p>
                @endif
            @endif
        </td>
    </tr>
</table>

<table class="context">
    <tr><td class="key">Lease</td><td class="value">{{ $invoice->lease?->reference ?? '-' }}</td></tr>
    <tr><td class="key">Property</td><td class="value">{{ $property?->name ?? '-' }}</td></tr>
    @if ($propertyAddress !== '')
        <tr><td class="key">Address</td><td>{{ $propertyAddress }}</td></tr>
    @endif
    <tr><td class="key">Unit</td><td class="value">{{ $invoice->lease?->unit?->name ?? '-' }}</td></tr>
</table>

<h2>Line items</h2>
<table class="items">
    <thead>
        <tr><th>Description</th><th>Type</th><th>Amount</th></tr>
    </thead>
    <tbody>
    @forelse ($invoice->lineItems as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td>{{ str($item->type)->replace('_', ' ')->title() }}</td>
            <td class="right">{{ $formatMoney($item->amount) }}</td>
        </tr>
    @empty
        <tr><td class="empty" colspan="3">No itemized charges.</td></tr>
    @endforelse
    </tbody>
</table>

<table class="totals">
    <tr><td>Total</td><td class="right">{{ $formatMoney($invoice->total) }}</td></tr>
    <tr><td>Amount paid</td><td class="right">{{ $formatMoney($invoice->amount_paid) }}</td></tr>
    <tr class="outstanding"><td>Outstanding</td><td class="right">{{ $formatMoney($invoice->outstanding) }}</td></tr>
</table>

@if ($payments->isNotEmpty())
    <section class="payment-details">
        <h2>{{ $payments->count() === 1 ? 'Payment Details' : 'Payments' }}</h2>
        @if ($payments->count() === 1)
            @php($payment = $payments->first())
            <table class="single">
                <tr>
                    <td><span class="label">Payment date</span><br>{{ $formatDate($payment->payment_date) }}</td>
                    <td><span class="label">Method</span><br>{{ str($payment->payment_method)->replace('_', ' ')->title() }}</td>
                    <td><span class="label">Reference</span><br>{{ $payment->reference_number ?? '-' }}</td>
                    @if ($payment->verified_at)
                        <td><span class="label">Verified at</span><br>{{ $formatDateTime($payment->verified_at) }}</td>
                    @endif
                </tr>
            </table>
        @else
            <table class="payments">
                <thead>
                    <tr><th>Payment date</th><th>Method</th><th>Reference</th><th>Amount</th></tr>
                </thead>
                <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $formatDate($payment->payment_date) }}</td>
                        <td>{{ str($payment->payment_method)->replace('_', ' ')->title() }}</td>
                        <td>{{ $payment->reference_number ?? '-' }}</td>
                        <td>{{ $formatMoney($payment->amount) }}</td>
                    </tr>
                @endforeach
                    <tr class="payment-total"><td colspan="3">Total paid</td><td>{{ $formatMoney($invoice->amount_paid) }}</td></tr>
                    <tr class="payment-total"><td colspan="3">Outstanding</td><td>{{ $formatMoney($invoice->outstanding) }}</td></tr>
                </tbody>
            </table>
        @endif
    </section>
@endif

<p class="footer">Generated on {{ $generatedAt }}. This document reflects the invoice status at the time it was generated.</p>
@if ($autoPrint ?? false)
    <script>
        window.addEventListener('load', () => window.print());
    </script>
@endif
</body>
</html>
