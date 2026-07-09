<?php

use App\Actions\Invoices\GenerateInvoices;
use App\Actions\Leases\MoveOutLease;
use App\Data\Lease\MoveOutLeaseData;
use App\Enums\InvoiceStatus;
use App\Events\Invoice\InvoiceGenerated;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Support\Facades\Event;

function createActiveLease(array $overrides = []): Lease
{
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();
    $tenant = Tenant::factory()->create();

    return Lease::factory()->create(array_merge([
        'unit_id' => $unit->id,
        'primary_tenant_id' => $tenant->id,
        'start_date' => now()->subMonths(2)->startOfMonth(),
        'end_date' => null,
        'rent_amount' => 1_500_000.00,
        'rent_due_day' => 5,
        'billing_interval' => 1,
        'billing_unit' => 'month',
        'status' => 'active',
    ], $overrides));
}

it('generates pending invoices with line items for each due period', function () {
    $lease = createActiveLease();

    $count = app(GenerateInvoices::class)->execute($lease);

    expect($count)->toBeGreaterThan(0)
        ->and($lease->invoices()->count())->toBe($count);

    $invoice = $lease->invoices()->orderBy('period_start')->first();

    expect($invoice->status)->toBe(InvoiceStatus::Pending)
        ->and((float) $invoice->total)->toBe(1_500_000.00)
        ->and((float) $invoice->amount_paid)->toBe(0.00)
        ->and($invoice->period_start->format('Y-m-d'))->toBe(now()->subMonths(2)->startOfMonth()->format('Y-m-d'))
        ->and($invoice->due_date->day)->toBe(5)
        ->and($invoice->reference)->toMatch('/^INV\d{8}$/');

    expect($invoice->lineItems)->toHaveCount(1)
        ->and($invoice->lineItems->first()->type)->toBe('rent')
        ->and((float) $invoice->lineItems->first()->amount)->toBe(1_500_000.00);
});

it('is idempotent across runs', function () {
    $lease = createActiveLease();
    $action = app(GenerateInvoices::class);

    $first = $action->execute($lease);
    $second = $action->execute($lease);

    expect($second)->toBe(0)
        ->and($lease->invoices()->count())->toBe($first);
});

it('respects the two month horizon', function () {
    $lease = createActiveLease();

    app(GenerateInvoices::class)->execute($lease);

    $horizon = now()->addMonthsNoOverflow(2)->endOfMonth();

    expect($lease->invoices()->whereDate('period_start', '>', $horizon)->count())->toBe(0);
});

it('does not generate past the lease end date', function () {
    $lease = createActiveLease([
        'start_date' => now()->subMonth()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
    ]);

    app(GenerateInvoices::class)->execute($lease);

    expect($lease->invoices()->whereDate('period_start', '>', $lease->end_date)->count())->toBe(0)
        ->and($lease->invoices()->count())->toBeGreaterThan(0);
});

it('generates for all active leases when no lease is given', function () {
    $active = createActiveLease();
    $terminated = createActiveLease(['status' => 'terminated']);

    app(GenerateInvoices::class)->execute();

    expect($active->invoices()->count())->toBeGreaterThan(0)
        ->and($terminated->invoices()->count())->toBe(0);
});

it('shifts due dates one billing period forward for arrears leases', function () {
    $advance = createActiveLease();
    $arrears = createActiveLease(['billing_strategy' => 'arrears']);

    app(GenerateInvoices::class)->execute($advance);
    app(GenerateInvoices::class)->execute($arrears);

    $advanceFirst = $advance->invoices()->orderBy('period_start')->first();
    $arrearsFirst = $arrears->invoices()->orderBy('period_start')->first();

    expect($advanceFirst->period_start->toDateString())
        ->toBe($arrearsFirst->period_start->toDateString())
        ->and($arrearsFirst->due_date->toDateString())
        ->toBe($advanceFirst->due_date->copy()->addMonthsNoOverflow(1)->toDateString());
});

it('dispatches InvoiceGenerated for each created invoice', function () {
    Event::fake([InvoiceGenerated::class]);

    $lease = createActiveLease();
    $count = app(GenerateInvoices::class)->execute($lease);

    Event::assertDispatchedTimes(InvoiceGenerated::class, $count);

    app(GenerateInvoices::class)->execute($lease);

    Event::assertDispatchedTimes(InvoiceGenerated::class, $count);
});

it('cancels future pending invoices on move out', function () {
    $lease = createActiveLease();
    app(GenerateInvoices::class)->execute($lease);

    $futureCount = $lease->invoices()->whereDate('period_start', '>', now())->count();
    expect($futureCount)->toBeGreaterThan(0);

    $action = app(MoveOutLease::class);
    $action->execute($lease, new MoveOutLeaseData(
        endDate: now()->format('Y-m-d'),
        terminationDate: now()->format('Y-m-d'),
        reason: 'Moving out',
    ));

    expect($lease->invoices()->whereDate('period_start', '>', now())
        ->where('status', InvoiceStatus::Cancelled->value)->count())->toBe($futureCount);
});
