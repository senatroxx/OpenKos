<?php

use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\ReferenceAllocationRetry;
use Illuminate\Database\QueryException;

it('retries lease allocation as a fresh transaction', function () {
    $existingLease = Lease::factory()->create();
    $attempts = 0;
    $rolledBackPropertyName = 'rolled-back-lease-property';

    $createdLease = app(ReferenceAllocationRetry::class)->run(function () use (&$attempts, $rolledBackPropertyName, $existingLease): Lease {
        $attempts++;

        if ($attempts === 1) {
            $property = Property::factory()->create(['name' => $rolledBackPropertyName]);
            $unit = Unit::factory()->for($property)->create();
            $tenant = Tenant::factory()->create();

            Lease::factory()->create([
                'reference' => $existingLease->reference,
                'unit_id' => $unit->id,
                'primary_tenant_id' => $tenant->id,
            ]);
        }

        return Lease::factory()->create();
    }, 'leases');

    expect($attempts)->toBe(2)
        ->and($createdLease->reference)->not->toBe($existingLease->reference)
        ->and(Property::query()->where('name', $rolledBackPropertyName)->exists())->toBeFalse()
        ->and(Lease::query()->count())->toBe(2);
});

it('retries maintenance ticket allocation as a fresh transaction', function () {
    $existingTicket = MaintenanceTicket::factory()->create();
    $attempts = 0;
    $rolledBackPropertyName = 'rolled-back-ticket-property';

    $createdTicket = app(ReferenceAllocationRetry::class)->run(function () use (&$attempts, $rolledBackPropertyName, $existingTicket): MaintenanceTicket {
        $attempts++;

        if ($attempts === 1) {
            $property = Property::factory()->create(['name' => $rolledBackPropertyName]);

            MaintenanceTicket::factory()->create([
                'property_id' => $property->id,
                'unit_id' => null,
                'reference' => $existingTicket->reference,
            ]);
        }

        return MaintenanceTicket::factory()->create();
    }, 'maintenance_tickets');

    expect($attempts)->toBe(2)
        ->and($createdTicket->reference)->not->toBe($existingTicket->reference)
        ->and(Property::query()->where('name', $rolledBackPropertyName)->exists())->toBeFalse()
        ->and(MaintenanceTicket::query()->count())->toBe(2);
});

it('does not retry unrelated unique constraint violations', function () {
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();
    $tenant = Tenant::factory()->create();
    $attempts = 0;
    $createdLeaseId = null;
    $exception = null;

    try {
        app(ReferenceAllocationRetry::class)->run(function () use (&$attempts, &$createdLeaseId, $unit, $tenant): void {
            $attempts++;
            $lease = Lease::factory()->create([
                'unit_id' => $unit->id,
                'primary_tenant_id' => $tenant->id,
            ]);
            $createdLeaseId = $lease->id;

            $lease->tenants()->attach($tenant->id, ['is_primary' => true]);
        }, 'leases');
    } catch (QueryException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(QueryException::class)
        ->and($attempts)->toBe(1)
        ->and(Lease::query()->whereKey($createdLeaseId)->exists())->toBeFalse();
});

it('surfaces a reference collision after three attempts', function () {
    $existingTicket = MaintenanceTicket::factory()->create();
    $attempts = 0;
    $exception = null;

    try {
        app(ReferenceAllocationRetry::class)->run(function () use (&$attempts, $existingTicket): void {
            $attempts++;

            MaintenanceTicket::factory()->create([
                'reference' => $existingTicket->reference,
            ]);
        }, 'maintenance_tickets');
    } catch (QueryException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(QueryException::class)
        ->and($attempts)->toBe(3)
        ->and(MaintenanceTicket::query()->count())->toBe(1);
});

it('keeps soft-deleted lease references reserved', function () {
    $lease = Lease::factory()->create();
    $reference = $lease->reference;

    $lease->delete();

    $nextLease = Lease::factory()->create();

    expect($nextLease->reference)->not->toBe($reference)
        ->and($nextLease->reference)->toEndWith('0002');
});

it('parses lease sequences beyond four digits numerically', function () {
    $year = now()->format('Y');

    Lease::factory()->create(['reference' => 'LSX'.$year.'9999']);
    Lease::factory()->create(['reference' => 'LSX'.$year.'10000']);

    $nextLease = Lease::factory()->create();

    expect($nextLease->reference)->toBe('LSX'.$year.'10001');
});
