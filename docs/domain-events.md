# Domain Events

> **Status:** Active
> **Purpose:** Catalog of domain events, their payload contracts, and dispatch points. Reference for plugin authors and core developers.

## Naming Convention

- All events live in `App\Events\{Domain}\` — one sub-namespace per aggregate.
- Event names are past-tense verb phrases: `{Entity}{PastAction}`.
- Events are immutable classes with `readonly` properties and `Dispatchable` trait.

## Event Catalog

### Lease

| Event | Payload | Dispatched from |
|---|---|---|
| `Lease\LeaseCreated` | `Lease $lease, array $tenantIds` | `Actions\Leases\CreateLease` |
| `Lease\LeaseStatusChanged` | `Lease $lease, LeaseStatus $from, LeaseStatus $to` | `Actions\Leases\MoveOutLease`, `Actions\Leases\RenewLease`, `LeaseController` |

**Payload: `LeaseCreated`**

```php
public readonly Lease $lease;
public readonly array $tenantIds;   // IDs of attached tenants
```

### Payment

| Event | Payload | Dispatched from |
|---|---|---|
| `Payment\PaymentRecorded` | `Payment $payment` | `Actions\Payments\RecordPayment` |
| `Payment\PaymentStatusChanged` | `Payment $payment, PaymentStatus $from, PaymentStatus $to` | `PaymentController::verify` |

### Maintenance

| Event | Payload | Dispatched from |
|---|---|---|
| `Maintenance\MaintenanceTicketCreated` | `MaintenanceTicket $ticket` | `MaintenanceTicketController::store` |
| `Maintenance\MaintenanceResolved` | `MaintenanceTicket $ticket` | `MaintenanceTicketController::update`, `Actions\Maintenance\ResolveTicket` |

### Unit

| Event | Payload | Dispatched from |
|---|---|---|
| `Unit\UnitStatusChanged` | `Unit $unit, UnitStatus $from, UnitStatus $to` | `CreateLease`, `MoveOutLease`, `BlockUnit`, `ResolveTicket` |

**Payload: `UnitStatusChanged`**

```php
public readonly Unit $unit;
public readonly UnitStatus $from;   // previous status
public readonly UnitStatus $to;     // new status
```

## Plugin Subscription

Plugins subscribe to events via `listens()` in their `Plugin` subclass:

```php
public function listens(): array
{
    return [
        App\Events\Lease\LeaseCreated::class => MyListener::class,
    ];
}
```

Listeners are wired onto Laravel's dispatcher by `PlatformServiceProvider::boot()` after all plugins have registered.

## Adding a New Event

1. Create the event class in `App\Events\{Domain}\`. Minimal boilerplate: `Dispatchable`, `readonly` constructor properties.
2. Dispatch it at the point of state change (Action or Controller, matching the existing pattern).
3. Document the event in this catalog.
4. Add a test in `tests/Feature/Domain/DomainEventTest.php`.

## Extension Guidance

- Events are the stable plugin API — never change a published event's payload without a major version bump.
- Prefer adding new properties as nullable (backward-compatible) over breaking changes.
- Keep events fired **after** the state change is committed (inside or after the DB transaction, matching the existing pattern).
