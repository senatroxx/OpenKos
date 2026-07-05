<?php

use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Unit;
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
        $unit = Unit::factory()->for($property)->create();
        $ticket = MaintenanceTicket::factory()->create([
            'unit_id' => $unit->id,
            'property_id' => $property->id,
        ]);

        expect($admin->can('view', $ticket))->toBeTrue();
    });

    it('denies admin not assigned to the property from viewing ticket', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();
        $ticket = MaintenanceTicket::factory()->create([
            'unit_id' => $unit->id,
            'property_id' => $propertyB->id,
        ]);

        expect($admin->can('view', $ticket))->toBeFalse();
    });
});
