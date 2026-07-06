<?php

namespace App\Listeners;

use App\Events\Lease\LeaseCreated;
use App\Events\Lease\LeaseStatusChanged;
use App\Events\Maintenance\MaintenanceResolved;
use App\Events\Maintenance\MaintenanceTicketCreated;
use App\Events\Payment\PaymentRecorded;
use App\Events\Payment\PaymentStatusChanged;
use App\Events\Unit\UnitStatusChanged;
use App\Models\ActivityLog;
use Illuminate\Events\Dispatcher;

class RecordActivitySubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            LeaseCreated::class => 'handleLeaseCreated',
            LeaseStatusChanged::class => 'handleLeaseStatusChanged',
            PaymentRecorded::class => 'handlePaymentRecorded',
            PaymentStatusChanged::class => 'handlePaymentStatusChanged',
            MaintenanceTicketCreated::class => 'handleMaintenanceTicketCreated',
            MaintenanceResolved::class => 'handleMaintenanceResolved',
            UnitStatusChanged::class => 'handleUnitStatusChanged',
        ];
    }

    public function handleLeaseCreated(LeaseCreated $event): void
    {
        ActivityLog::record(
            event: 'lease.created',
            subject: $event->lease,
            metadata: ['tenant_ids' => $event->tenantIds],
            actorId: $event->actorId,
        );
    }

    public function handleLeaseStatusChanged(LeaseStatusChanged $event): void
    {
        ActivityLog::record(
            event: 'lease.status_changed',
            subject: $event->lease,
            metadata: [
                'from' => $event->from->value,
                'to' => $event->to->value,
            ],
            actorId: $event->actorId,
        );
    }

    public function handlePaymentRecorded(PaymentRecorded $event): void
    {
        ActivityLog::record(
            event: 'payment.recorded',
            subject: $event->payment,
            metadata: [
                'amount' => $event->payment->amount,
                'method' => $event->payment->payment_method,
                'status' => $event->payment->status,
            ],
            actorId: $event->actorId,
        );
    }

    public function handlePaymentStatusChanged(PaymentStatusChanged $event): void
    {
        ActivityLog::record(
            event: 'payment.status_changed',
            subject: $event->payment,
            metadata: [
                'from' => $event->from->value,
                'to' => $event->to->value,
            ],
            actorId: $event->actorId,
        );
    }

    public function handleMaintenanceTicketCreated(MaintenanceTicketCreated $event): void
    {
        ActivityLog::record(
            event: 'maintenance.ticket_created',
            subject: $event->ticket,
            actorId: $event->actorId,
        );
    }

    public function handleMaintenanceResolved(MaintenanceResolved $event): void
    {
        ActivityLog::record(
            event: 'maintenance.resolved',
            subject: $event->ticket,
            actorId: $event->actorId,
        );
    }

    public function handleUnitStatusChanged(UnitStatusChanged $event): void
    {
        ActivityLog::record(
            event: 'unit.status_changed',
            subject: $event->unit,
            metadata: [
                'from' => $event->from->value,
                'to' => $event->to->value,
            ],
            actorId: $event->actorId,
        );
    }
}
