<?php

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('create operations', function () {
    it('records an audit when a model is created', function () {
        $property = Property::factory()->create();

        expect(AuditLog::count())->toBe(1);
        $log = AuditLog::first();
        expect($log)
            ->auditable_type->toBe($property->getMorphClass())
            ->auditable_id->toBe($property->id)
            ->operation->toBe('create')
            ->before->toBeNull()
            ->after->toHaveKeys(['name', 'address', 'phone'])
            ->actor_id->toBeNull();
    });

    it('records the authenticated user as actor on create', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);

        Property::factory()->create();

        $log = AuditLog::first();
        expect($log)
            ->actor_id->toBe($user->id)
            ->actor_type->toBe($user->getMorphClass());
    });

    it('allows multiple auditable models independently', function () {
        Property::factory()->count(2)->create();

        expect(AuditLog::count())->toBe(2);
    });
});

describe('update operations', function () {
    it('records only dirty fields on update', function () {
        $property = Property::factory()->create();
        AuditLog::truncate();

        $property->update(['name' => 'Updated Name', 'phone' => '123456789']);

        expect(AuditLog::count())->toBe(1);
        $log = AuditLog::first();
        expect($log)
            ->operation->toBe('update')
            ->before->toHaveKeys(['name', 'phone'])
            ->after->toHaveKeys(['name', 'phone']);
        expect($log->after['name'])->toBe('Updated Name');
        expect($log->after['phone'])->toBe('***MASKED***');
    });

    it('skips audit when no fields changed', function () {
        $property = Property::factory()->create();
        AuditLog::truncate();

        $property->touch();

        expect(AuditLog::count())->toBe(0);
    });
});

describe('delete and restore operations', function () {
    it('records a delete operation when soft deleted', function () {
        $property = Property::factory()->create();
        AuditLog::truncate();

        $property->delete();

        expect(AuditLog::count())->toBe(1);
        $log = AuditLog::first();
        expect($log)
            ->operation->toBe('delete')
            ->before->toHaveKeys(['name', 'address'])
            ->after->toBeNull();
    });

    it('records a restore operation without duplicate update entry', function () {
        $property = Property::factory()->create();
        $property->delete();
        AuditLog::truncate();

        $property->restore();

        expect(AuditLog::count())->toBe(1);
        $log = AuditLog::first();
        expect($log)
            ->operation->toBe('restore')
            ->before->toBeNull()
            ->after->toHaveKeys(['name', 'address']);
    });
});

describe('immutability', function () {
    it('does not set updated_at on audit logs', function () {
        Property::factory()->create();

        expect(AuditLog::first()->updated_at)->toBeNull();
    });
});

describe('PII handling', function () {
    it('masks sensitive fields on Tenant', function () {
        Tenant::factory()->create([
            'phone' => '08123456789',
            'email' => 'tenant@example.com',
            'id_card_number' => '1234567890123456',
        ]);

        $log = AuditLog::first();
        expect($log->after['phone'])->toBe('***MASKED***');
        expect($log->after['email'])->toBe('***MASKED***');
        expect($log->after['id_card_number'])->toBe('***MASKED***');
        expect($log->after['name'])->not->toBe('***MASKED***');
    });

    it('masks phone on Property', function () {
        Property::factory()->create(['phone' => '021123456']);

        $log = AuditLog::first();
        expect($log->after['phone'])->toBe('***MASKED***');
    });
});
