<?php

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('tenant can list their own maintenance tickets', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $ticket1 = MaintenanceTicket::factory()->create([
        'created_by' => $user->id,
        'property_id' => $lease->unit->property_id,
        'unit_id' => $lease->unit_id,
        'title' => 'Leaking faucet',
        'created_at' => now()->subDay(),
    ]);

    $ticket2 = MaintenanceTicket::factory()->create([
        'created_by' => $user->id,
        'property_id' => $lease->unit->property_id,
        'unit_id' => $lease->unit_id,
        'title' => 'Broken lock',
        'created_at' => now(),
    ]);

    // Another tenant's ticket
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->withUser($otherUser)->create();
    $otherLease = Lease::factory()->create(['primary_tenant_id' => $otherTenant->id]);
    MaintenanceTicket::factory()->create([
        'created_by' => $otherUser->id,
        'property_id' => $otherLease->unit->property_id,
        'unit_id' => $otherLease->unit_id,
        'title' => 'Broken window',
    ]);

    $this->actingAs($user)
        ->get(route('portal.maintenance-tickets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/maintenance-tickets/index')
            ->has('tickets.data', 2)
            ->where('tickets.data.0.title', 'Broken lock')
            ->where('tickets.data.1.title', 'Leaking faucet')
            ->has('activeLease')
            ->where('activeLease.property_id', $lease->unit->property_id));
});

test('tenant can submit a maintenance ticket if they have an active lease', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->post(route('portal.maintenance-tickets.store'), [
            'title' => 'Leaking faucet',
            'description' => 'Bathroom sink is leaking',
            'location_type' => 'unit',
        ])
        ->assertRedirect();

    $ticket = MaintenanceTicket::first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->title)->toBe('Leaking faucet')
        ->and($ticket->description)->toBe('Bathroom sink is leaking')
        ->and($ticket->priority->value)->toBe(MaintenancePriority::Medium->value)
        ->and($ticket->status->value)->toBe(MaintenanceStatus::Reported->value)
        ->and($ticket->created_by)->toBe($user->id)
        ->and($ticket->property_id)->toBe($lease->unit->property_id)
        ->and($ticket->unit_id)->toBe($lease->unit_id)
        ->and($ticket->location)->toBeNull();
});

test('tenant can submit a maintenance ticket for common area if they have an active lease', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->post(route('portal.maintenance-tickets.store'), [
            'title' => 'Broken lobby light',
            'description' => 'First floor lobby light is blinking',
            'location_type' => 'area',
            'location' => 'Lobby',
        ])
        ->assertRedirect();

    $ticket = MaintenanceTicket::first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->title)->toBe('Broken lobby light')
        ->and($ticket->description)->toBe('First floor lobby light is blinking')
        ->and($ticket->priority->value)->toBe(MaintenancePriority::Medium->value)
        ->and($ticket->status->value)->toBe(MaintenanceStatus::Reported->value)
        ->and($ticket->created_by)->toBe($user->id)
        ->and($ticket->property_id)->toBe($lease->unit->property_id)
        ->and($ticket->unit_id)->toBeNull()
        ->and($ticket->location)->toBe('Lobby');
});

test('tenant with a whole-property lease can submit property-scoped maintenance', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $property = Property::factory()->create(['rental_mode' => 'whole_property']);
    $rate = PropertyRate::factory()->for($property)->create();
    $lease = Lease::factory()->wholeProperty($property)->create([
        'property_rate_id' => $rate->id,
        'primary_tenant_id' => $tenant->id,
    ]);

    $this->actingAs($user)
        ->post(route('portal.maintenance-tickets.store'), [
            'title' => 'Roof leak',
            'location_type' => 'property',
        ])
        ->assertRedirect();

    $ticket = MaintenanceTicket::firstOrFail();

    expect($ticket->property_id)->toBe($property->id)
        ->and($ticket->unit_id)->toBeNull()
        ->and($ticket->location)->toBeNull()
        ->and($lease->unit_id)->toBeNull();
});

test('tenant cannot submit a maintenance ticket without an active lease', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();

    // Non-active lease
    Lease::factory()->terminated()->create(['primary_tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->post(route('portal.maintenance-tickets.store'), [
            'title' => 'Leaking faucet',
            'location_type' => 'unit',
        ])
        ->assertSessionHasErrors(['title']);

    expect(MaintenanceTicket::count())->toBe(0);
});

test('tenant can view details of their own ticket', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $ticket = MaintenanceTicket::factory()->create([
        'created_by' => $user->id,
        'property_id' => $lease->unit->property_id,
        'unit_id' => null,
        'location' => 'Lobby',
        'title' => 'Leaking faucet',
        'description' => 'Leaking sink',
        'status' => MaintenanceStatus::InProgress->value,
    ]);

    $this->actingAs($user)
        ->get(route('portal.maintenance-tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/maintenance-tickets/show')
            ->where('ticket.id', $ticket->id)
            ->where('ticket.title', 'Leaking faucet')
            ->where('ticket.description', 'Leaking sink')
            ->where('ticket.location', 'Lobby')
            ->where('ticket.status', MaintenanceStatus::InProgress->value));
});

test('tenant cannot view details of another tenants ticket', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->withUser($otherUser)->create();
    $otherLease = Lease::factory()->create(['primary_tenant_id' => $otherTenant->id]);

    $ticket = MaintenanceTicket::factory()->create([
        'created_by' => $otherUser->id,
        'property_id' => $otherLease->unit->property_id,
        'unit_id' => $otherLease->unit_id,
        'title' => 'Leaking faucet',
    ]);

    $this->actingAs($user)
        ->get(route('portal.maintenance-tickets.show', $ticket))
        ->assertForbidden();
});
