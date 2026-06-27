<?php

use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;

describe('Property', function () {
    it('has many rooms', function () {
        $property = Property::factory()
            ->has(Room::factory()->count(3))
            ->create();

        expect($property->rooms)->toHaveCount(3);
    });

    it('auto-generates slug from name', function () {
        $property = Property::factory()->create(['name' => 'Kos Melati']);

        expect($property->slug)->toBe('kos-melati');
    });

    it('appends suffix when slug already exists', function () {
        $first = Property::factory()->create(['name' => 'Kos Melati']);
        $second = Property::factory()->create(['name' => 'Kos Melati']);
        $third = Property::factory()->create(['name' => 'Kos Melati']);

        expect($first->slug)->toBe('kos-melati');
        expect($second->slug)->toBe('kos-melati-1');
        expect($third->slug)->toBe('kos-melati-2');
    });

    it('belongs to many users', function () {
        $property = Property::factory()->create();
        $user = User::factory()->create();

        $property->users()->attach($user);

        expect($property->users)->toHaveCount(1);
        expect($user->properties)->toHaveCount(1);
    });

    it('can be soft deleted', function () {
        $property = Property::factory()->create();
        $property->delete();

        expect(Property::count())->toBe(0);
        expect(Property::withTrashed()->count())->toBe(1);
    });
});

describe('Room', function () {
    it('belongs to a property', function () {
        $property = Property::factory()->create();
        $room = Room::factory()->for($property)->create();

        expect($room->property)->toBeInstanceOf(Property::class);
        expect($room->property->id)->toBe($property->id);
    });

    it('has many leases', function () {
        $room = Room::factory()
            ->has(Lease::factory()->count(2))
            ->create();

        expect($room->leases)->toHaveCount(2);
    });

    it('has many maintenance tickets', function () {
        $room = Room::factory()
            ->has(MaintenanceTicket::factory()->count(2))
            ->create();

        expect($room->maintenanceTickets)->toHaveCount(2);
    });

    it('can be soft deleted', function () {
        $room = Room::factory()->create();
        $room->delete();

        expect(Room::count())->toBe(0);
        expect(Room::withTrashed()->count())->toBe(1);
    });
});

describe('Tenant', function () {
    it('can be created without a user account', function () {
        $tenant = Tenant::factory()->create();

        expect($tenant->user_id)->toBeNull();
        expect($tenant->user)->toBeNull();
    });

    it('can be linked to a user account', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->withUser($user)->create();

        expect($tenant->user_id)->toBe($user->id);
        expect($tenant->user)->toBeInstanceOf(User::class);
    });

    it('has many leases', function () {
        $tenant = Tenant::factory()
            ->has(Lease::factory()->count(2))
            ->create();

        expect($tenant->leases)->toHaveCount(2);
    });

    it('can be soft deleted', function () {
        $tenant = Tenant::factory()->create();
        $tenant->delete();

        expect(Tenant::count())->toBe(0);
        expect(Tenant::withTrashed()->count())->toBe(1);
    });
});

describe('Lease', function () {
    it('has many tenants via pivot', function () {
        $lease = Lease::factory()->create();

        expect($lease->tenants)->toHaveCount(1);
        expect($lease->tenants->first()->id)->toBe($lease->primary_tenant_id);
    });

    it('has a primary tenant', function () {
        $lease = Lease::factory()->create();

        expect($lease->primaryTenant)->toBeInstanceOf(Tenant::class);
        expect($lease->primaryTenant->id)->toBe($lease->primary_tenant_id);
    });

    it('belongs to a room', function () {
        $room = Room::factory()->create();
        $lease = Lease::factory()->for($room)->create();

        expect($lease->room)->toBeInstanceOf(Room::class);
        expect($lease->room->id)->toBe($room->id);
    });

    it('has many payments', function () {
        $lease = Lease::factory()
            ->has(Payment::factory()->count(3))
            ->create();

        expect($lease->payments)->toHaveCount(3);
    });

    it('has active status by default', function () {
        $lease = Lease::factory()->create();

        expect($lease->status)->toBe('active');
    });

    it('can be terminated', function () {
        $lease = Lease::factory()->terminated()->create();

        expect($lease->status)->toBe('terminated');
        expect($lease->termination_date)->not->toBeNull();
        expect($lease->termination_reason)->not->toBeNull();
    });

    it('can override rent amount', function () {
        $lease = Lease::factory()->create(['rent_amount' => 1_500_000]);

        expect((float) $lease->rent_amount)->toBe(1500000.00);
    });

    it('can be soft deleted', function () {
        $lease = Lease::factory()->create();
        $lease->delete();

        expect(Lease::count())->toBe(0);
        expect(Lease::withTrashed()->count())->toBe(1);
    });
});

describe('Payment', function () {
    it('belongs to a paymentable (lease)', function () {
        $lease = Lease::factory()->create();
        $payment = Payment::factory()->create([
            'paymentable_id' => $lease->id,
            'paymentable_type' => Lease::class,
        ]);

        expect($payment->paymentable)->toBeInstanceOf(Lease::class);
        expect($payment->paymentable->id)->toBe($lease->id);
    });

    it('can be confirmed by a user', function () {
        $user = User::factory()->create();
        $payment = Payment::factory()->create(['confirmed_by' => $user->id]);

        expect($payment->confirmedBy)->toBeInstanceOf(User::class);
        expect($payment->confirmedBy->id)->toBe($user->id);
    });

    it('can be recorded by a user', function () {
        $user = User::factory()->create();
        $payment = Payment::factory()->create(['recorded_by' => $user->id]);

        expect($payment->recordedBy)->toBeInstanceOf(User::class);
        expect($payment->recordedBy->id)->toBe($user->id);
    });
});

describe('MaintenanceTicket', function () {
    it('belongs to a room', function () {
        $room = Room::factory()->create();
        $ticket = MaintenanceTicket::factory()->for($room)->create();

        expect($ticket->room)->toBeInstanceOf(Room::class);
        expect($ticket->room->id)->toBe($room->id);
    });

    it('can be assigned to a user', function () {
        $user = User::factory()->create();
        $ticket = MaintenanceTicket::factory()->create(['assigned_to' => $user->id]);

        expect($ticket->assignedTo)->toBeInstanceOf(User::class);
        expect($ticket->assignedTo->id)->toBe($user->id);
    });
});

describe('User tenant profile', function () {
    it('hasTenantProfile returns true when linked to a tenant', function () {
        $user = User::factory()->create();
        Tenant::factory()->withUser($user)->create();

        expect($user->hasTenantProfile())->toBeTrue();
    });

    it('hasTenantProfile returns false when not linked', function () {
        $user = User::factory()->create();

        expect($user->hasTenantProfile())->toBeFalse();
    });
});

describe('Property user pivot', function () {
    it('attaches users to properties', function () {
        $property = Property::factory()->create();
        $users = User::factory()->count(2)->create();

        $property->users()->attach($users);

        expect($property->users()->count())->toBe(2);
        foreach ($users as $user) {
            expect($user->properties()->count())->toBe(1);
        }
    });
});
