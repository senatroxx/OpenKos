<?php

use App\Enums\LeaseStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Events\Lease\LeaseCreated;
use App\Events\Lease\LeaseStatusChanged;
use App\Events\Maintenance\MaintenanceResolved;
use App\Events\Maintenance\MaintenanceTicketCreated;
use App\Events\Payment\PaymentRecorded;
use App\Events\Payment\PaymentStatusChanged;
use App\Events\Unit\UnitStatusChanged;
use App\Models\ActivityLog;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('lease events', function () {
    it('records lease.created', function () {
        $lease = Lease::factory()->create();
        $actor = User::factory()->owner()->create();

        LeaseCreated::dispatch($lease, [$lease->primary_tenant_id], actorId: $actor->id);

        expect(ActivityLog::count())->toBe(1);
        $log = ActivityLog::first();
        expect($log)
            ->event->toBe('lease.created')
            ->subject_type->toBe($lease->getMorphClass())
            ->subject_id->toBe($lease->id)
            ->actor_id->toBe($actor->id)
            ->metadata->toBe(['tenant_ids' => [$lease->primary_tenant_id]]);
    });

    it('records lease.status_changed', function () {
        $lease = Lease::factory()->create(['status' => LeaseStatus::Active]);
        $actor = User::factory()->owner()->create();

        LeaseStatusChanged::dispatch($lease, LeaseStatus::Active, LeaseStatus::Terminated, actorId: $actor->id);

        expect(ActivityLog::count())->toBe(1);
        $log = ActivityLog::first();
        expect($log)
            ->event->toBe('lease.status_changed')
            ->subject_type->toBe($lease->getMorphClass())
            ->subject_id->toBe($lease->id)
            ->actor_id->toBe($actor->id)
            ->metadata->toBe(['from' => LeaseStatus::Active->value, 'to' => LeaseStatus::Terminated->value]);
    });
});

describe('payment events', function () {
    it('records payment.recorded', function () {
        $payment = Payment::factory()->create([
            'amount' => 150000,
            'payment_method' => 'cash',
            'status' => PaymentStatus::Pending,
        ]);
        $actor = User::factory()->owner()->create();

        PaymentRecorded::dispatch($payment, actorId: $actor->id);

        expect(ActivityLog::count())->toBe(1);
        $log = ActivityLog::first();
        expect($log)
            ->event->toBe('payment.recorded')
            ->subject_type->toBe($payment->getMorphClass())
            ->subject_id->toBe($payment->id)
            ->actor_id->toBe($actor->id)
            ->metadata->toBe([
                'amount' => '150000.00',
                'method' => 'cash',
                'status' => PaymentStatus::Pending->value,
            ]);
    });

    it('records payment.status_changed', function () {
        $payment = Payment::factory()->create();
        $actor = User::factory()->owner()->create();

        PaymentStatusChanged::dispatch($payment, PaymentStatus::Pending, PaymentStatus::Confirmed, actorId: $actor->id);

        expect(ActivityLog::count())->toBe(1);
        $log = ActivityLog::first();
        expect($log)
            ->event->toBe('payment.status_changed')
            ->metadata->toBe(['from' => PaymentStatus::Pending->value, 'to' => PaymentStatus::Confirmed->value]);
    });
});

describe('maintenance events', function () {
    it('records maintenance.ticket_created', function () {
        $ticket = MaintenanceTicket::factory()->create();
        $actor = User::factory()->owner()->create();

        MaintenanceTicketCreated::dispatch($ticket, actorId: $actor->id);

        expect(ActivityLog::count())->toBe(1);
        $log = ActivityLog::first();
        expect($log)
            ->event->toBe('maintenance.ticket_created')
            ->subject_type->toBe($ticket->getMorphClass())
            ->subject_id->toBe($ticket->id)
            ->actor_id->toBe($actor->id);
    });

    it('records maintenance.resolved', function () {
        $ticket = MaintenanceTicket::factory()->create(['status' => MaintenanceStatus::Resolved]);
        $actor = User::factory()->owner()->create();

        MaintenanceResolved::dispatch($ticket, actorId: $actor->id);

        expect(ActivityLog::count())->toBe(1);
        $log = ActivityLog::first();
        expect($log)
            ->event->toBe('maintenance.resolved')
            ->subject_type->toBe($ticket->getMorphClass())
            ->subject_id->toBe($ticket->id)
            ->actor_id->toBe($actor->id);
    });
});

describe('unit events', function () {
    it('records unit.status_changed', function () {
        $unit = Unit::factory()->create(['status' => UnitStatus::Available]);
        $actor = User::factory()->owner()->create();

        UnitStatusChanged::dispatch($unit, UnitStatus::Available, UnitStatus::Occupied, actorId: $actor->id);

        expect(ActivityLog::count())->toBe(1);
        $log = ActivityLog::first();
        expect($log)
            ->event->toBe('unit.status_changed')
            ->subject_type->toBe($unit->getMorphClass())
            ->subject_id->toBe($unit->id)
            ->actor_id->toBe($actor->id)
            ->metadata->toBe(['from' => UnitStatus::Available->value, 'to' => UnitStatus::Occupied->value]);
    });
});

describe('actor behavior', function () {
    it('allows null actor_id', function () {
        $lease = Lease::factory()->create();

        LeaseCreated::dispatch($lease, [$lease->primary_tenant_id]);

        expect(ActivityLog::count())->toBe(1);
        expect(ActivityLog::first()->actor_id)->toBeNull();
    });
});
