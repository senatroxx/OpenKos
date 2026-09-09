<?php

use App\Actions\Invoices\GenerateInvoices;
use App\Actions\Utility\CreateUtilityReadingCorrection;
use App\Actions\Utility\RecordUtilityReading;
use App\Actions\Utility\UpdateUtilityReading;
use App\Enums\InvoiceStatus;
use App\Models\InvoiceLineItem;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityMeter;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Setting::set('supported_currencies', ['IDR', 'USD']);
});

function makeUtilityLease(array $overrides = []): Lease
{
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();
    $tenant = Tenant::factory()->create();

    return Lease::factory()->create(array_merge([
        'unit_id' => $unit->id,
        'primary_tenant_id' => $tenant->id,
        'start_date' => CarbonImmutable::today()->startOfMonth(),
        'end_date' => null,
        'rent_amount' => '1000000',
        'rent_due_day' => 1,
        'billing_interval' => 1,
        'billing_unit' => 'month',
        'status' => 'active',
    ], $overrides));
}

it('bills only eligible unit readings within partial lease boundaries', function () {
    $leaseEnd = CarbonImmutable::today()->startOfMonth()->addDays(14);
    $lease = makeUtilityLease(['end_date' => $leaseEnd]);
    $meter = UtilityMeter::factory()->for($lease->unit)->create([
        'rate' => '1000',
        'currency' => 'IDR',
    ]);
    $boundaryMeter = UtilityMeter::factory()->for($lease->unit)->create([
        'identifier' => 'BOUNDARY-001',
        'rate' => '1000',
        'currency' => 'IDR',
    ]);
    $crossingMeter = UtilityMeter::factory()->for($lease->unit)->create([
        'identifier' => 'CROSSING-001',
        'rate' => '1000',
        'currency' => 'IDR',
    ]);
    $otherUnit = Unit::factory()->create();
    $otherMeter = UtilityMeter::factory()->for($otherUnit)->create([
        'identifier' => 'OTHER-001',
        'rate' => '1000',
        'currency' => 'IDR',
    ]);
    $record = app(RecordUtilityReading::class);

    $eligible = $record->execute($meter, [
        'reading_date' => $leaseEnd->subDays(2)->toDateString(),
        'period_start' => $lease->start_date->addDays(2)->toDateString(),
        'period_end' => $leaseEnd->subDays(2)->toDateString(),
        'previous_reading' => '0',
        'current_reading' => '100',
    ]);
    $crossing = $record->execute($crossingMeter, [
        'reading_date' => $leaseEnd->toDateString(),
        'period_start' => $leaseEnd->subDay()->toDateString(),
        'period_end' => $leaseEnd->addDay()->toDateString(),
        'previous_reading' => '0',
        'current_reading' => '50',
    ]);
    $otherReading = $record->execute($otherMeter, [
        'reading_date' => $leaseEnd->toDateString(),
        'period_start' => $lease->start_date->toDateString(),
        'period_end' => $leaseEnd->toDateString(),
        'previous_reading' => '0',
        'current_reading' => '75',
    ]);
    $record->execute($boundaryMeter, [
        'reading_date' => $lease->start_date->subDay()->toDateString(),
        'period_start' => $lease->start_date->subMonth()->startOfMonth()->toDateString(),
        'period_end' => $lease->start_date->subDay()->toDateString(),
        'previous_reading' => '0',
        'current_reading' => '20',
    ]);
    $boundaryReading = $record->execute($boundaryMeter, [
        'reading_date' => $lease->start_date->addDay()->toDateString(),
        'period_start' => $lease->start_date->toDateString(),
        'period_end' => $lease->start_date->addDays(2)->toDateString(),
        'previous_reading' => '20',
        'current_reading' => '50',
    ]);

    expect(app(GenerateInvoices::class)->execute($lease))->toBe(1);

    $eligibleLineItem = $eligible->refresh()->invoiceLineItem;
    $crossingLineItem = $crossing->refresh()->invoiceLineItem;

    expect($eligibleLineItem)->not->toBeNull()
        ->and($eligibleLineItem->type)->toBe('utility')
        ->and($crossingLineItem)->toBeNull()
        ->and($otherReading->refresh()->invoiceLineItem)->toBeNull()
        ->and($boundaryReading->refresh()->invoiceLineItem)->toBeNull();
});

it('uses the reading rate snapshot, copies metadata, and is idempotent', function () {
    $lease = makeUtilityLease();
    $meter = UtilityMeter::factory()->for($lease->unit)->create([
        'rate' => '1000',
        'currency' => 'IDR',
    ]);
    $reading = app(RecordUtilityReading::class)->execute($meter, [
        'reading_date' => $lease->start_date->endOfMonth()->toDateString(),
        'period_start' => $lease->start_date->startOfMonth()->toDateString(),
        'period_end' => $lease->start_date->endOfMonth()->toDateString(),
        'previous_reading' => '0',
        'current_reading' => '100',
        'reference' => 'manual-reading-001',
    ]);

    $meter->update([
        'utility_type' => $meter->utility_type->value,
        'identifier' => $meter->identifier,
        'measurement_unit' => $meter->measurement_unit,
        'rate' => '2000',
        'currency' => 'IDR',
        'is_active' => true,
    ]);

    expect(app(GenerateInvoices::class)->execute($lease))->toBeGreaterThan(0);

    $invoice = $lease->invoices()->orderBy('period_start')->firstOrFail();
    $lineItem = $reading->refresh()->invoiceLineItem;

    expect($lineItem)->not->toBeNull()
        ->and($lineItem->amount)->toBe('100000.000')
        ->and($lineItem->metadata['rate'])->toBe('1000.000')
        ->and($lineItem->metadata['currency'])->toBe('IDR')
        ->and($lineItem->metadata['reading_reference'])->toBe('manual-reading-001');

    expect(app(GenerateInvoices::class)->execute($lease))->toBe(0)
        ->and($invoice->lineItems()->where('utility_reading_id', $reading->id)->count())->toBe(1);
});

it('bills corrections as signed deltas linked to the original bill', function () {
    $lease = makeUtilityLease();
    $meter = UtilityMeter::factory()->for($lease->unit)->create([
        'rate' => '1000',
        'currency' => 'IDR',
    ]);
    $reading = app(RecordUtilityReading::class)->execute($meter, [
        'reading_date' => $lease->start_date->endOfMonth()->toDateString(),
        'period_start' => $lease->start_date->startOfMonth()->toDateString(),
        'period_end' => $lease->start_date->endOfMonth()->toDateString(),
        'previous_reading' => '0',
        'current_reading' => '100',
    ]);

    app(GenerateInvoices::class)->execute($lease);
    $invoice = $lease->invoices()->firstOrFail();
    $originalLineItem = $reading->refresh()->invoiceLineItem;

    expect(fn () => app(UpdateUtilityReading::class)->execute($reading, [
        'current_reading' => '90',
    ]))->toThrow(ValidationException::class);

    $correction = app(CreateUtilityReadingCorrection::class)->execute($reading, [
        'current_reading' => '90',
        'reference' => 'corrected-meter-entry',
    ]);

    app(GenerateInvoices::class)->execute($lease);
    $correctionLineItem = $correction->refresh()->invoiceLineItem;

    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('properties.units.utilities', [$lease->unit->property, $lease->unit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('meters.0.readings.0.id', $correction->id)
            ->where('meters.0.readings.0.correction_exists', false)
            ->where('meters.0.readings.1.id', $reading->id)
            ->where('meters.0.readings.1.correction_exists', true)
        );

    expect($correction->adjustment_consumption)->toBe('-10.000')
        ->and($correctionLineItem)->not->toBeNull()
        ->and($correctionLineItem->amount)->toBe('-10000.000')
        ->and($correctionLineItem->metadata['adjustment_consumption'])->toBe('-10.000')
        ->and($correctionLineItem->metadata['corrects_reading_id'])->toBe($reading->id)
        ->and($correctionLineItem->metadata['original_invoice_line_item_id'])->toBe($originalLineItem->id)
        ->and($reading->refresh()->current_reading)->toBe('100.000')
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Pending)
        ->and(InvoiceLineItem::query()->where('utility_reading_id', $correction->id)->count())->toBe(1);
});

it('exposes billed readings as read-only in the unit workspace', function () {
    $user = User::factory()->owner()->create();
    $lease = makeUtilityLease();
    $meter = UtilityMeter::factory()->for($lease->unit)->create();
    $reading = app(RecordUtilityReading::class)->execute($meter, [
        'reading_date' => $lease->start_date->endOfMonth()->toDateString(),
        'period_start' => $lease->start_date->startOfMonth()->toDateString(),
        'period_end' => $lease->start_date->endOfMonth()->toDateString(),
        'previous_reading' => '0',
        'current_reading' => '100',
    ]);

    app(GenerateInvoices::class)->execute($lease);

    $this->actingAs($user)
        ->get(route('properties.units.utilities', [$lease->unit->property, $lease->unit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('meters.0.readings.0.id', $reading->id)
            ->where('meters.0.readings.0.billed', true)
            ->where('meters.0.readings.0.can_edit', false)
            ->where('meters.0.readings.0.can_delete', false)
        );
});
