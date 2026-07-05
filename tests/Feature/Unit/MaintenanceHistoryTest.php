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
    it('redirects unauthenticated users to login', function () {
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $this->get(route('properties.units.maintenance-history', [$property, $unit]))
            ->assertRedirect('login');
    });

    it('returns 403 for users without maintenance-tickets.view permission', function () {
        $user = User::factory()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $this->actingAs($user)
            ->get(route('properties.units.maintenance-history', [$property, $unit]))
            ->assertForbidden();
    });

    it('allows admin with permission to view maintenance history', function () {
        $user = User::factory()->admin()->create();
        $user->givePermissionTo('maintenance-tickets.view');
        $property = Property::factory()->create();
        $user->properties()->sync([$property->id]);
        $unit = Unit::factory()->for($property)->create();

        $this->actingAs($user)
            ->get(route('properties.units.maintenance-history', [$property, $unit]))
            ->assertOk();
    });

    it('allows owner to view maintenance history', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $this->actingAs($user)
            ->get(route('properties.units.maintenance-history', [$property, $unit]))
            ->assertOk();
    });
});

describe('maintenance history page', function () {
    it('shows maintenance tickets for the unit', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $ticket = MaintenanceTicket::factory()->create([
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'title' => 'Broken AC',
            'cost' => 50000,
        ]);

        $response = $this->actingAs($user)
            ->get(route('properties.units.maintenance-history', [$property, $unit]))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('properties/units/maintenance-history')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.title', 'Broken AC')
        );
    });

    it('does not show tickets from other units', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $otherUnit = Unit::factory()->for($property)->create();

        MaintenanceTicket::factory()->create([
            'property_id' => $property->id,
            'unit_id' => $otherUnit->id,
            'title' => 'Other unit ticket',
        ]);

        $this->actingAs($user)
            ->get(route('properties.units.maintenance-history', [$property, $unit]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tickets.data', 0)
            );
    });

    it('shows empty state for unit with no tickets', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $this->actingAs($user)
            ->get(route('properties.units.maintenance-history', [$property, $unit]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/units/maintenance-history')
                ->has('tickets.data', 0)
            );
    });

    it('paginates tickets', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        MaintenanceTicket::factory()->count(20)->create([
            'property_id' => $property->id,
            'unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('properties.units.maintenance-history', [$property, $unit]))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('tickets.data', 15)
            ->has('tickets.current_page')
        );
    });

    it('orders tickets by most recent first', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $old = MaintenanceTicket::factory()->create([
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'created_at' => now()->subDays(2),
        ]);
        $new = MaintenanceTicket::factory()->create([
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('properties.units.maintenance-history', [$property, $unit]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tickets.data.0.id', $new->id)
                ->where('tickets.data.1.id', $old->id)
            );
    });
});
