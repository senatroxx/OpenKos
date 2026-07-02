<?php

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceTicket;
use App\Models\Property;
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
