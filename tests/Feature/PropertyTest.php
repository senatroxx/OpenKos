<?php

use App\Models\Property;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        $this->get(route('properties.index'))->assertRedirect('login');
    });

    it('returns 403 for users without properties.manage permission', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertForbidden();
    });

    it('allows admin to access properties', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk();
    });

    it('allows owner to access properties', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk();
    });

    it('denies staff access to properties', function () {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertForbidden();
    });
});

describe('CRUD', function () {
    it('lists properties on the index page', function () {
        $user = User::factory()->owner()->create();
        Property::factory()->count(3)->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/index')
                ->has('properties.data', 3)
            );
    });

    it('creates a property', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)->post(route('properties.store'), [
            'name' => 'Kos Melati',
            'city' => 'Jakarta',
        ]);

        $property = Property::first();

        expect($property)->not->toBeNull();
        expect($property->name)->toBe('Kos Melati');
        expect($property->city)->toBe('Jakarta');
    });

    it('validates required fields on create', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('properties.store'), [])
            ->assertSessionHasErrors('name');
    });

    it('updates a property', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create(['name' => 'Kos Melati']);

        $this->actingAs($user)
            ->put(route('properties.update', $property), [
                'name' => 'Kos Mawar',
            ]);

        $property->refresh();

        expect($property->name)->toBe('Kos Mawar');
    });

    it('archives a property via soft delete', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)->delete(route('properties.destroy', $property));

        expect(Property::count())->toBe(0);
        expect(Property::withTrashed()->count())->toBe(1);
    });
});
