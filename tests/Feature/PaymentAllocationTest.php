<?php

use App\Actions\Invoices\AllocatePayment;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

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
        'status' => \App\Enums\PaymentStatus::Confirmed,
    ]);

    app(AllocatePayment::class)->execute($payment);

    expect($payment->allocations()->count())->toBe(1);
    expect($payment->allocations->first()->amount)->toBe('500000.00');
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
        'status' => \App\Enums\PaymentStatus::Confirmed,
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
        'status' => \App\Enums\PaymentStatus::Confirmed,
    ]);

    app(AllocatePayment::class)->execute($payment);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
});
