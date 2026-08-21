<?php

use App\Actions\Invoices\AllocatePayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('creates allocation records when recording a confirmed payment', function () {
    $user = User::factory()->owner()->create();
    $lease = Lease::factory()->create();
    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'total' => 1_000_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 500_000,
        'status' => PaymentStatus::Confirmed,
    ]);

    app(AllocatePayment::class)->execute($payment);

    expect($payment->allocations()->count())->toBe(1);
    expect($payment->allocations->first()->amount)->toBe('500000.000');
});

it('marks invoice as partial after partial payment', function () {
    $lease = Lease::factory()->create();
    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'total' => 1_000_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);
    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 500_000,
        'status' => PaymentStatus::Confirmed,
    ]);

    app(AllocatePayment::class)->execute($payment);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Partial);
    expect((float) $invoice->amount_paid)->toBe(500000.0);
});

it('marks invoice as paid after full payment', function () {
    $lease = Lease::factory()->create();
    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'total' => 1_000_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);
    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 1_000_000,
        'status' => PaymentStatus::Confirmed,
    ]);

    app(AllocatePayment::class)->execute($payment);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
});

it('keeps a payment on its recorded invoice instead of settling an older one', function () {
    $lease = Lease::factory()->create();
    $olderInvoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'due_date' => now()->startOfMonth()->addDays(4),
        'total' => 1_000_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);
    $targetInvoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'period_start' => now()->addMonth()->startOfMonth(),
        'period_end' => now()->addMonth()->endOfMonth(),
        'due_date' => now()->addMonth()->startOfMonth()->addDays(4),
        'total' => 1_000_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);
    $payment = Payment::factory()->create([
        'invoice_id' => $targetInvoice->id,
        'amount' => 1_000_000,
        'status' => PaymentStatus::Confirmed,
    ]);

    app(AllocatePayment::class)->execute($payment);

    expect($payment->allocations()->count())->toBe(1)
        ->and((int) $payment->allocations()->first()->invoice_id)->toBe($targetInvoice->id)
        ->and($olderInvoice->fresh()->status)->toBe(InvoiceStatus::Pending)
        ->and((float) $olderInvoice->fresh()->amount_paid)->toBe(0.0)
        ->and($targetInvoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and((float) $targetInvoice->fresh()->amount_paid)->toBe(1000000.0);
});

it('recalculates affected invoices with one confirmed payment aggregate', function () {
    $lease = Lease::factory()->create();
    $invoiceA = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'due_date' => '2026-01-05',
        'total' => 1_000_000,
    ]);
    $invoiceB = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'period_start' => '2026-02-01',
        'period_end' => '2026-02-28',
        'due_date' => '2026-02-05',
        'total' => 1_000_000,
    ]);
    $targetInvoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-31',
        'due_date' => '2026-03-05',
        'total' => 1_000_000,
    ]);

    Payment::factory()->create([
        'invoice_id' => $invoiceA->id,
        'amount' => 250_000,
    ]);
    Payment::factory()->create([
        'invoice_id' => $invoiceB->id,
        'amount' => 500_000,
    ]);
    $payment = Payment::factory()->create([
        'invoice_id' => $targetInvoice->id,
        'amount' => 1_000_000,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoiceA->id,
        'amount' => 500_000,
    ]);
    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoiceB->id,
        'amount' => 500_000,
    ]);

    $aggregateQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$aggregateQueries): void {
        $sql = strtolower($query->sql);

        if (str_contains($sql, 'sum(') && str_contains($sql, 'payments')) {
            $aggregateQueries++;
        }
    });

    app(AllocatePayment::class)->execute($payment);

    expect($aggregateQueries)->toBe(1)
        ->and($invoiceA->fresh()->status)->toBe(InvoiceStatus::Partial)
        ->and((float) $invoiceA->fresh()->amount_paid)->toBe(250000.0)
        ->and($invoiceB->fresh()->status)->toBe(InvoiceStatus::Partial)
        ->and((float) $invoiceB->fresh()->amount_paid)->toBe(500000.0)
        ->and($targetInvoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and((float) $targetInvoice->fresh()->amount_paid)->toBe(1000000.0);
});
