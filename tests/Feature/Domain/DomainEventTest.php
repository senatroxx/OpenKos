<?php

use App\Enums\LeaseStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\UnitStatus;
use App\Events\Lease\LeaseCreated;
use App\Events\Maintenance\MaintenanceResolved;
use App\Events\Maintenance\MaintenanceTicketCreated;
use App\Events\Payment\PaymentRecorded;
use App\Events\Unit\UnitStatusChanged;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

describe('lease events', function () {
    it('dispatches LeaseCreated when creating a lease', function () {
        Event::fake([LeaseCreated::class, UnitStatusChanged::class]);

        $property = Property::factory()->create();
        $unit = Unit::factory()->withRate(1_000_000)->create(['property_id' => $property->id]);
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.units.leases.store', [$property, $unit]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => '2026-06-01',
            'rent_amount' => 1_500_000,
            'billing_unit' => 'month',
            'billing_interval' => 1,
        ]);

        Event::assertDispatched(LeaseCreated::class);
        Event::assertDispatched(UnitStatusChanged::class);
    });

    it('dispatches UnitStatusChanged when lease termination frees a unit', function () {
        Event::fake([UnitStatusChanged::class]);

        $property = Property::factory()->create();
        $unit = Unit::factory()->withRate(1_000_000)->occupied()->create(['property_id' => $property->id]);
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => LeaseStatus::Active,
        ]);

        $this->actingAs($user)
            ->delete(route('properties.units.leases.destroy', [$property, $unit, $lease]), ['reason' => 'test']);

        // ponytail: UnitStatusChanged only fires when the status actually changes.
        // The unit starts occupied and termination flips it to available.
        Event::assertDispatched(UnitStatusChanged::class);
    });
});

describe('payment events', function () {
    it('dispatches PaymentRecorded when recording a payment', function () {
        Event::fake([PaymentRecorded::class]);

        $property = Property::factory()->create();
        $unit = Unit::factory()->withRate(1_000_000)->create(['property_id' => $property->id]);
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => LeaseStatus::Active,
        ]);

        $this->actingAs($user)->post(route('leases.payments.store', $lease), [
            'amount' => 1_500_000,
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d'),
            'period_month' => now()->month,
            'period_year' => now()->year,
        ]);

        Event::assertDispatched(PaymentRecorded::class);
    });
});

describe('maintenance events', function () {
    it('dispatches MaintenanceTicketCreated when creating a ticket', function () {
        Event::fake([MaintenanceTicketCreated::class]);

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('maintenance-tickets.store'), [
                'property_id' => Property::factory()->create()->id,
                'title' => 'Broken window',
                'priority' => MaintenancePriority::Medium->value,
            ])
            ->assertRedirect();

        Event::assertDispatched(MaintenanceTicketCreated::class);
    });

    it('dispatches MaintenanceResolved when resolving a ticket', function () {
        Event::fake([MaintenanceResolved::class]);

        $ticket = MaintenanceTicket::factory()->create([
            'status' => MaintenanceStatus::InProgress->value,
        ]);
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->put(route('maintenance-tickets.update', $ticket), [
                'status' => MaintenanceStatus::Resolved->value,
            ])
            ->assertRedirect();

        Event::assertDispatched(MaintenanceResolved::class);
    });

    it('dispatches UnitStatusChanged when blocking a unit', function () {
        Event::fake([UnitStatusChanged::class]);

        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $this->actingAs($owner)
            ->post(route('maintenance-tickets.store'), [
                'property_id' => $property->id,
                'unit_id' => $unit->id,
                'title' => 'Broken ceiling',
                'priority' => MaintenancePriority::Urgent->value,
                'block_unit' => true,
            ])
            ->assertRedirect();

        Event::assertDispatched(UnitStatusChanged::class);
    });

    it('dispatches UnitStatusChanged when restoring unit after resolution', function () {
        Event::fake([UnitStatusChanged::class]);

        $owner = User::factory()->owner()->create();
        $unit = Unit::factory()->create(['status' => UnitStatus::Maintenance]);

        $ticket = MaintenanceTicket::factory()->create([
            'unit_id' => $unit->id,
            'status' => MaintenanceStatus::InProgress->value,
        ]);

        $this->actingAs($owner)
            ->put(route('maintenance-tickets.update', $ticket), [
                'status' => MaintenanceStatus::Resolved->value,
                'restore_unit' => true,
            ])
            ->assertRedirect();

        Event::assertDispatched(UnitStatusChanged::class);
    });
});
