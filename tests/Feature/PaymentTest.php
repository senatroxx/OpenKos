<?php

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('authorization', function () {
    it('allows owner to view payment', function () {
        $user = User::factory()->owner()->create();
        $payment = Payment::factory()->create();

        expect($user->can('view', $payment))->toBeTrue();
    });

    it('allows admin assigned to the property to view payment', function () {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $admin->properties()->sync([$property->id]);
        $room = Room::factory()->for($property)->create();
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create(['room_id' => $room->id, 'primary_tenant_id' => $tenant->id]);
        $payment = Payment::factory()->create(['lease_id' => $lease->id]);

        expect($admin->can('view', $payment))->toBeTrue();
    });

    it('denies admin not assigned to the property from viewing payment', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $room = Room::factory()->for($propertyB)->create();
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create(['room_id' => $room->id, 'primary_tenant_id' => $tenant->id]);
        $payment = Payment::factory()->create(['lease_id' => $lease->id]);

        expect($admin->can('view', $payment))->toBeFalse();
    });
});
