<?php

use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('authorization', function () {
    it('allows owner to view ticket', function () {
        $user = User::factory()->owner()->create();
        $ticket = MaintenanceTicket::factory()->create();

        expect($user->can('view', $ticket))->toBeTrue();
    });

    it('allows admin assigned to the property to view ticket', function () {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $admin->properties()->sync([$property->id]);
        $room = Room::factory()->for($property)->create();
        $ticket = MaintenanceTicket::factory()->create(['room_id' => $room->id]);

        expect($admin->can('view', $ticket))->toBeTrue();
    });

    it('denies admin not assigned to the property from viewing ticket', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $room = Room::factory()->for($propertyB)->create();
        $ticket = MaintenanceTicket::factory()->create(['room_id' => $room->id]);

        expect($admin->can('view', $ticket))->toBeFalse();
    });
});
