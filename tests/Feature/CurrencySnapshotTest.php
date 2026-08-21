<?php

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

it('snapshots currency through the billing chain at creation', function () {
    Setting::set('currency', 'USD');
    $lease = Lease::factory()->create();
    $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);
    $payment = Payment::factory()->for($invoice)->create(['amount' => '12.50']);

    expect($lease->currency)->toBe('USD')
        ->and($invoice->currency)->toBe('USD')
        ->and($payment->currency)->toBe('USD');
});

it('keeps non-null snapshots immutable', function () {
    $invoice = Invoice::factory()->create(['currency' => 'USD']);

    expect(fn () => $invoice->update(['currency' => 'IDR']))
        ->toThrow(LogicException::class);
});

it('resolves legacy null currency through current settings without persisting it', function () {
    Setting::set('currency', 'IDR');
    $invoice = Invoice::factory()->create();
    DB::table('invoices')->whereKey($invoice->id)->update(['currency' => null]);

    expect($invoice->fresh()->currency)->toBe('IDR');

    expect(DB::table('invoices')->whereKey($invoice->id)->value('currency'))->toBeNull();
});

it('resolves legacy null currency using a later configured currency', function () {
    Setting::set('currency', 'USD');
    $invoice = Invoice::factory()->create();
    DB::table('invoices')->whereKey($invoice->id)->update(['currency' => null]);

    expect($invoice->fresh()->currency)->toBe('USD')
        ->and(DB::table('invoices')->whereKey($invoice->id)->value('currency'))->toBeNull();
});
