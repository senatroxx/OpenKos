<?php

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\RoomStatus;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
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

it('blocks room when creating a ticket', function () {
    $owner = User::factory()->owner()->create();
    $room = Room::factory()->create();

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.store'), [
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'title' => 'Broken window',
            'priority' => MaintenancePriority::High->value,
            'block_room' => true,
        ])
        ->assertRedirect();

    expect($room->fresh()->status)->toBe(RoomStatus::Maintenance);
});

it('blocks occupied room and warns about active lease', function () {
    $owner = User::factory()->owner()->create();
    $room = Room::factory()->create();
    $tenant = Tenant::factory()->create();
    $room->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $room->leases()->first()->tenants()->attach($tenant->id, ['is_primary' => DB::raw('true')]);

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.store'), [
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'title' => 'Broken AC',
            'priority' => MaintenancePriority::High->value,
            'block_room' => true,
        ])
        ->assertRedirect();

    expect($room->fresh()->status)->toBe(RoomStatus::Maintenance);
    expect($room->leases()->where('status', 'active')->count())->toBe(1);
});

it('moves tenant and blocks room when move_tenant_to_room_id provided', function () {
    $owner = User::factory()->owner()->create();
    $room = Room::factory()->create();
    $targetRoom = Room::factory()->create(['property_id' => $room->property_id]);
    $tenant = Tenant::factory()->create();
    $lease = $room->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $lease->tenants()->attach($tenant->id, ['is_primary' => DB::raw('true')]);

    $this->actingAs($owner)
        ->post(route('maintenance-tickets.store'), [
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'title' => 'Broken ceiling',
            'priority' => MaintenancePriority::Urgent->value,
            'block_room' => true,
            'move_tenant_to_room_id' => $targetRoom->id,
        ])
        ->assertRedirect();

    expect($room->fresh()->status)->toBe(RoomStatus::Maintenance);
    expect($lease->fresh()->status)->toBe('terminated');
    expect($targetRoom->fresh()->status)->toBe(RoomStatus::Occupied);
    expect($targetRoom->leases()->where('status', 'active')->count())->toBe(1);
});

it('prevents leasing a maintenance room', function () {
    $owner = User::factory()->owner()->create();
    $room = Room::factory()->create(['status' => RoomStatus::Maintenance]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($owner)
        ->post(route('properties.rooms.leases.store', [$room->property_id, $room]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => now()->format('Y-m-d'),
            'rent_amount' => 1_000_000,
        ])
        ->assertStatus(422);
});

it('prevents moving into a maintenance room', function () {
    $owner = User::factory()->owner()->create();
    $sourceRoom = Room::factory()->create();
    $targetRoom = Room::factory()->create(['property_id' => $sourceRoom->property_id, 'status' => RoomStatus::Maintenance]);
    $tenant = Tenant::factory()->create();
    $lease = $sourceRoom->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $lease->tenants()->attach($tenant->id, ['is_primary' => DB::raw('true')]);

    $this->actingAs($owner)
        ->post(route('properties.rooms.leases.move', [$sourceRoom->property_id, $sourceRoom, $lease]), [
            'target_room_id' => $targetRoom->id,
        ])
        ->assertStatus(422);
});

it('prevents assigning tenant to maintenance room', function () {
    $owner = User::factory()->owner()->create();
    $room = Room::factory()->create(['status' => RoomStatus::Maintenance]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($owner)
        ->post(route('tenants.assign-room', $tenant), [
            'room_id' => $room->id,
            'start_date' => now()->format('Y-m-d'),
            'rent_amount' => 1_000_000,
        ])
        ->assertStatus(422);
});

it('restores room availability when resolving ticket with restore_room flag', function () {
    $owner = User::factory()->owner()->create();
    $room = Room::factory()->create(['status' => RoomStatus::Maintenance]);
    $ticket = MaintenanceTicket::factory()->create([
        'room_id' => $room->id,
        'status' => MaintenanceStatus::InProgress->value,
    ]);

    $this->actingAs($owner)
        ->put(route('maintenance-tickets.update', $ticket), [
            'status' => MaintenanceStatus::Resolved->value,
            'restore_room' => true,
        ])
        ->assertRedirect();

    expect($room->fresh()->status)->toBe(RoomStatus::Available);
});

it('does not restore room when other tickets still open', function () {
    $owner = User::factory()->owner()->create();
    $room = Room::factory()->create(['status' => RoomStatus::Maintenance]);
    $ticket1 = MaintenanceTicket::factory()->create([
        'room_id' => $room->id,
        'status' => MaintenanceStatus::InProgress->value,
    ]);
    MaintenanceTicket::factory()->create([
        'room_id' => $room->id,
        'status' => MaintenanceStatus::Reported->value,
    ]);

    $this->actingAs($owner)
        ->put(route('maintenance-tickets.update', $ticket1), [
            'status' => MaintenanceStatus::Resolved->value,
            'restore_room' => true,
        ])
        ->assertRedirect();

    expect($room->fresh()->status)->toBe(RoomStatus::Maintenance);
});

it('excludes maintenance rooms from available rooms', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $property->users()->attach($owner->id);
    Room::factory()->create(['property_id' => $property->id, 'status' => RoomStatus::Maintenance, 'name' => 'Maintenance Room']);
    Room::factory()->create(['property_id' => $property->id, 'status' => RoomStatus::Available, 'name' => 'Available Room']);

    $response = $this->actingAs($owner)
        ->get(route('properties.rooms.index', $property));

    $response->assertOk();
    $available = collect($response->viewData('page')['props']['availableRooms']);
    expect($available->count())->toBe(1);
    expect($available->first()['name'])->toBe('Available Room');
});

it('preserves maintenance status on lease termination', function () {
    $owner = User::factory()->owner()->create();
    $room = Room::factory()->create(['status' => RoomStatus::Maintenance]);
    $tenant = Tenant::factory()->create();
    $lease = $room->leases()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_000_000,
        'status' => 'active',
    ]);
    $lease->tenants()->attach($tenant->id, ['is_primary' => DB::raw('true')]);

    $this->actingAs($owner)
        ->delete(route('properties.rooms.leases.destroy', [$room->property_id, $room, $lease]), [
            'reason' => 'Testing',
        ])
        ->assertRedirect();

    expect($room->fresh()->status)->toBe(RoomStatus::Maintenance);
});
