<?php

use App\Actions\Invoices\GenerateInvoices;
use App\Actions\Leases\CreateLease;
use App\Actions\Leases\MoveOutLease;
use App\Data\Lease\CreateLeaseData;
use App\Data\Lease\MoveOutLeaseData;
use App\Enums\LeaseStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

it('inherits the default rate currency when no rate is selected', function () {
    Setting::set('currency', 'IDR');
    $unit = Unit::factory()->create();
    $unit->rates()->update([
        'amount' => '12.50',
        'currency' => 'USD',
    ]);
    $tenant = Tenant::factory()->create();

    $lease = app(CreateLease::class)->execute($unit, new CreateLeaseData(
        tenantIds: [$tenant->id],
        startDate: '2026-08-01',
        endDate: null,
        rentAmount: null,
        billingInterval: null,
        billingUnit: null,
        billingStrategy: null,
        unitRateId: null,
        depositAmount: null,
        depositPaidAt: null,
        depositRefundAmount: null,
        depositRefundedAt: null,
        rentDueDay: 1,
        notes: null,
    ));

    expect($lease->currency)->toBe('USD')
        ->and($lease->rent_amount)->toBe('12.500');
});

it('rejects generating invoices from legacy amounts invalid for the resolved currency', function () {
    Setting::set('currency', 'IDR');
    $lease = Lease::factory()->create([
        'rent_amount' => '1.50',
        'start_date' => '2026-08-01',
    ]);
    DB::table('leases')->whereKey($lease->id)->update(['currency' => null]);

    expect(fn () => app(GenerateInvoices::class)->execute($lease->fresh()))
        ->toThrow(InvalidArgumentException::class, 'more precision');

    expect($lease->invoices()->count())->toBe(0);
});

it('rejects moving a lease onto a rate with a different currency', function () {
    Setting::set('currency', 'IDR');
    $sourceUnit = Unit::factory()->create();
    $targetUnit = Unit::factory()->create();
    $tenant = Tenant::factory()->create();
    $lease = Lease::factory()->create([
        'unit_id' => $sourceUnit->id,
        'primary_tenant_id' => $tenant->id,
        'currency' => 'USD',
        'status' => LeaseStatus::Active,
    ]);

    expect(fn () => app(MoveOutLease::class)->execute($lease, new MoveOutLeaseData(
        terminationDate: '2026-08-21',
        endDate: '2026-08-21',
        reason: 'Currency mismatch test',
        moveToAnotherUnit: true,
        targetUnitId: $targetUnit->id,
    )))->toThrow(HttpException::class);

    expect($lease->fresh()->status)->toBe(LeaseStatus::Active)
        ->and(Lease::query()->where('unit_id', $targetUnit->id)->exists())->toBeFalse();
});
