<?php

use App\Enums\LeaseStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\UnitStatus;
use App\Models\LeaseUnitHistory;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('creates a maintenance ticket', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.store'), [
            'property_id' => Property::factory()->create()->id,
            'title' => 'Broken water pipe',
            'description' => 'Water is leaking',
            'priority' => MaintenancePriority::High->value,
        ])
        ->assertRedirect();

    $ticket = MaintenanceTicket::first();

    expect($ticket)->not->toBeNull();
    expect($ticket->title)->toBe('Broken water pipe');
    expect($ticket->status)->toBe(MaintenanceStatus::Reported);
    expect($ticket->priority)->toBe(MaintenancePriority::High);
    expect($ticket->created_by)->toBe($owner->id);
});

it('validates required fields', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.store'), [])
        ->assertSessionHasErrors(['property_id', 'title', 'priority']);
});

it('updates ticket fields', function () {
    $ticket = MaintenanceTicket::factory()->create([
        'status' => MaintenanceStatus::Reported->value,
    ]);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->put(route('maintenance-tickets.update', $ticket), [
            'title' => 'Updated title',
            'priority' => MaintenancePriority::Urgent->value,
        ])
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->title)->toBe('Updated title');
    expect($ticket->priority)->toBe(MaintenancePriority::Urgent);
});

it('deletes a ticket', function () {
    $ticket = MaintenanceTicket::factory()->create();
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->delete(route('maintenance-tickets.destroy', $ticket))
        ->assertRedirect();

    expect(MaintenanceTicket::count())->toBe(0);
});

it('reported to in_progress', function () {
    $ticket = MaintenanceTicket::factory()->create([
        'status' => MaintenanceStatus::Reported->value,
    ]);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->put(route('maintenance-tickets.update', $ticket), [
            'status' => MaintenanceStatus::InProgress->value,
        ])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(MaintenanceStatus::InProgress);
});

it('in_progress to resolved', function () {
    $ticket = MaintenanceTicket::factory()->create([
        'status' => MaintenanceStatus::InProgress->value,
    ]);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->put(route('maintenance-tickets.update', $ticket), [
            'status' => MaintenanceStatus::Resolved->value,
        ])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(MaintenanceStatus::Resolved);
});

it('rejects invalid transition', function () {
    $ticket = MaintenanceTicket::factory()->create([
        'status' => MaintenanceStatus::Reported->value,
    ]);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->put(route('maintenance-tickets.update', $ticket), [
            'status' => MaintenanceStatus::Resolved->value,
        ])
        ->assertStatus(422);
});

it('staff can self-assign', function () {
    $staffRole = SpatieRole::findOrCreate('staff');
    $staffRole->givePermissionTo(Permission::findOrCreate('maintenance-tickets.update'));

    $ticket = MaintenanceTicket::factory()->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->post(route('maintenance-tickets.assign', $ticket), [
            'assigned_to' => $staff->id,
        ])
        ->assertForbidden();
});

it('owner can assign anyone', function () {
    $ticket = MaintenanceTicket::factory()->create();
    $owner = User::factory()->owner()->create();
    $staff = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.assign', $ticket), [
            'assigned_to' => $staff->id,
        ])
        ->assertRedirect();

    expect($ticket->fresh()->assigned_to)->toBe($staff->id);
});

it('blocks unit when creating a ticket', function () {
    $owner = User::factory()->owner()->create();
    $unit = Unit::factory()->create();

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.store'), [
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'title' => 'Broken window',
            'priority' => MaintenancePriority::High->value,
            'block_unit' => true,
        ])
        ->assertRedirect();

    expect($unit->fresh()->status)->toBe(UnitStatus::Maintenance);
});

it('blocks occupied unit and warns about active lease', function () {
    $owner = User::factory()->owner()->create();
    $unit = Unit::factory()->create();
    $tenant = Tenant::factory()->create();
    $unit->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $unit->leases()->first()->tenants()->attach($tenant->id, ['is_primary' => true]);

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.store'), [
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'title' => 'Broken AC',
            'priority' => MaintenancePriority::High->value,
            'block_unit' => true,
        ])
        ->assertRedirect();

    expect($unit->fresh()->status)->toBe(UnitStatus::Maintenance);
    expect($unit->leases()->where('status', 'active')->count())->toBe(1);
});

it('moves tenant and blocks unit when move_tenant_to_unit_id provided', function () {
    $owner = User::factory()->owner()->create();
    $unit = Unit::factory()->create();
    $targetUnit = Unit::factory()->create(['property_id' => $unit->property_id]);
    $tenant = Tenant::factory()->create();
    $lease = $unit->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $lease->tenants()->attach($tenant->id, ['is_primary' => true]);

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.store'), [
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'title' => 'Broken ceiling',
            'priority' => MaintenancePriority::Urgent->value,
            'block_unit' => true,
            'move_tenant_to_unit_id' => $targetUnit->id,
        ])
        ->assertRedirect();

    expect($unit->fresh()->status)->toBe(UnitStatus::Maintenance);
    expect($lease->fresh()->status)->toBe(LeaseStatus::Active);
    expect($lease->fresh()->unit_id)->toBe($targetUnit->id);
    expect($targetUnit->fresh()->status)->toBe(UnitStatus::Occupied);
    expect($targetUnit->leases()->where('status', LeaseStatus::Active->value)->count())->toBe(1);
    expect($lease->fresh()->unitHistories()->count())->toBe(1);
});

it('keeps the tenant on the same unit when blocking without a move target', function () {
    // Guards the "Keep tenant, just mark as maintenance" path: the occupied-unit
    // dialog must NOT send move_tenant_to_unit_id, even if a destination was
    // browsed. With no move target, the tenant stays put.
    $owner = User::factory()->owner()->create();
    $unit = Unit::factory()->create();
    // A valid move target exists but must not be used.
    Unit::factory()->create(['property_id' => $unit->property_id]);
    $tenant = Tenant::factory()->create();
    $lease = $unit->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $lease->tenants()->attach($tenant->id, ['is_primary' => true]);

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.store'), [
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'title' => 'Broken heater',
            'priority' => MaintenancePriority::High->value,
            'block_unit' => true,
            // no move_tenant_to_unit_id
        ])
        ->assertRedirect();

    expect($unit->fresh()->status)->toBe(UnitStatus::Maintenance);
    expect($lease->fresh()->status)->toBe(LeaseStatus::Active);
    expect($lease->fresh()->unit_id)->toBe($unit->id); // not moved
});

it('prevents leasing a maintenance unit', function () {
    $owner = User::factory()->owner()->create();
    $unit = Unit::factory()->create(['status' => UnitStatus::Maintenance]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($owner)
        ->post(route('properties.units.leases.store', [$unit->property, $unit]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => now()->format('Y-m-d'),
            'rent_amount' => 1_000_000,
        ])
        ->assertStatus(422);
});

it('prevents moving into a maintenance unit', function () {
    $owner = User::factory()->owner()->create();
    $sourceUnit = Unit::factory()->create();
    $targetUnit = Unit::factory()->create(['property_id' => $sourceUnit->property_id, 'status' => UnitStatus::Maintenance]);
    $tenant = Tenant::factory()->create();
    $lease = $sourceUnit->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $lease->tenants()->attach($tenant->id, ['is_primary' => true]);

    $this->actingAs($owner)
        ->post(route('properties.units.leases.move', [$sourceUnit->property, $sourceUnit, $lease]), [
            'target_unit_id' => $targetUnit->id,
        ])
        ->assertStatus(422);
});

it('prevents assigning tenant to maintenance unit', function () {
    $owner = User::factory()->owner()->create();
    $unit = Unit::factory()->create(['status' => UnitStatus::Maintenance]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($owner)
        ->post(route('tenants.assign-unit', $tenant), [
            'unit_id' => $unit->id,
            'start_date' => now()->format('Y-m-d'),
            'rent_amount' => 1_000_000,
        ])
        ->assertStatus(422);
});

it('restores unit availability when resolving ticket with restore_unit flag', function () {
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

    expect($unit->fresh()->status)->toBe(UnitStatus::Available);
});

it('does not restore unit when other tickets still open', function () {
    $owner = User::factory()->owner()->create();
    $unit = Unit::factory()->create(['status' => UnitStatus::Maintenance]);
    $ticket1 = MaintenanceTicket::factory()->create([
        'unit_id' => $unit->id,
        'status' => MaintenanceStatus::InProgress->value,
    ]);
    MaintenanceTicket::factory()->create([
        'unit_id' => $unit->id,
        'status' => MaintenanceStatus::Reported->value,
    ]);

    $this->actingAs($owner)
        ->put(route('maintenance-tickets.update', $ticket1), [
            'status' => MaintenanceStatus::Resolved->value,
            'restore_unit' => true,
        ])
        ->assertRedirect();

    expect($unit->fresh()->status)->toBe(UnitStatus::Maintenance);
});

it('excludes maintenance units from available units', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $property->users()->attach($owner->id);
    Unit::factory()->create(['property_id' => $property->id, 'status' => UnitStatus::Maintenance, 'name' => 'Maintenance Unit']);
    Unit::factory()->create(['property_id' => $property->id, 'status' => UnitStatus::Available, 'name' => 'Available Unit']);

    $response = $this->actingAs($owner)
        ->get(route('properties.units.index', $property));

    $response->assertOk();
    $available = collect($response->viewData('page')['props']['availableUnits']);
    expect($available->count())->toBe(1);
    expect($available->first()['name'])->toBe('Available Unit');
});

it('preserves maintenance status on lease termination', function () {
    $owner = User::factory()->owner()->create();
    $unit = Unit::factory()->create(['status' => UnitStatus::Maintenance]);
    $tenant = Tenant::factory()->create();
    $lease = $unit->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $lease->tenants()->attach($tenant->id, ['is_primary' => true]);

    $this->actingAs($owner)
        ->delete(route('properties.units.leases.destroy', [$unit->property, $unit, $lease]), [
            'reason' => 'Testing',
        ])
        ->assertRedirect();

    expect($unit->fresh()->status)->toBe(UnitStatus::Maintenance);
});

it('moves tenant back when resolving ticket with move_back flag', function () {
    $owner = User::factory()->owner()->create();
    $unit = Unit::factory()->create(['status' => UnitStatus::Maintenance]);
    $targetUnit = Unit::factory()->create([
        'property_id' => $unit->property_id,
        'status' => UnitStatus::Occupied,
    ]);
    $tenant = Tenant::factory()->create();
    $lease = $targetUnit->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $lease->tenants()->attach($tenant->id, ['is_primary' => true]);

    LeaseUnitHistory::create([
        'lease_id' => $lease->id,
        'from_unit_id' => $unit->id,
        'to_unit_id' => $targetUnit->id,
        'reason' => 'maintenance',
        'effective_date' => now(),
    ]);

    $ticket = MaintenanceTicket::factory()->create([
        'unit_id' => $unit->id,
        'status' => MaintenanceStatus::InProgress->value,
    ]);

    $this->actingAs($owner)
        ->put(route('maintenance-tickets.update', $ticket), [
            'status' => MaintenanceStatus::Resolved->value,
            'restore_unit' => true,
            'move_back' => true,
        ])
        ->assertRedirect();

    expect($unit->fresh()->status)->toBe(UnitStatus::Occupied);
    expect($lease->fresh()->unit_id)->toBe($unit->id);
    expect($targetUnit->fresh()->status)->toBe(UnitStatus::Available);
});
