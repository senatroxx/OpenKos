# Domain Events

> **Status:** Active
> **Purpose:** Catalog of domain events, their payload contracts, and dispatch points. Reference for plugin authors and core developers.

## Naming Convention

- All events live in `App\Events\{Domain}\` — one sub-namespace per aggregate.
- Event names are past-tense verb phrases: `{Entity}{PastAction}`.
- Events are immutable classes with `readonly` properties and `Dispatchable` trait.

## Event Catalog

### Lease

| Event                      | Payload                                                               | Dispatched from                                                                                           |
| -------------------------- | --------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| `Lease\LeaseCreated`       | `Lease $lease, array $tenantIds, ?int $actorId`                      | `LeaseController::store`                                                                                  |
| `Lease\LeaseStatusChanged` | `Lease $lease, LeaseStatus $from, LeaseStatus $to, ?int $actorId`    | `LeaseController::moveOut`, `LeaseController::move`, `LeaseController::renew`, `LeaseController::destroy` |

**Payload: `LeaseCreated`**

```php
public readonly Lease $lease;
public readonly array $tenantIds;   // IDs of attached tenants
public readonly ?int $actorId;      // ID of the acting user, null for system-triggered
```

### Payment

| Event                          | Payload                                                                 | Dispatched from             |
| ------------------------------ | ----------------------------------------------------------------------- | --------------------------- |
| `Payment\PaymentRecorded`      | `Payment $payment, ?int $actorId`                                       | `PaymentController::store`  |
| `Payment\PaymentStatusChanged` | `Payment $payment, PaymentStatus $from, PaymentStatus $to, ?int $actorId` | `PaymentController::verify` |

Since [ADR-007](architecture/adr/007-invoice-aggregate.md) payments settle invoices; listeners reach the billing context via `$payment->invoice` (and `->invoice->lease`).

### Invoice

| Event                      | Payload            | Dispatched from                     |
| -------------------------- | ------------------ | ----------------------------------- |
| `Invoice\InvoiceGenerated` | `Invoice $invoice` | `GenerateInvoices` (post-commit)    |
| `Invoice\InvoiceFullyPaid` | `Invoice $invoice` | `AllocatePayment`                   |

`InvoiceGenerated` is the plugin hook for payment gateways, the tenant portal, and accounting — it fires once per invoice the generation engine creates, after the transaction commits, and never on reruns (generation is idempotent).

`InvoiceFullyPaid` fires when an invoice's status transitions to `Paid` for the first time after a payment allocation. Other invoice lifecycle events (status changed, cancelled) don't exist yet — add them when a consumer appears.

### Maintenance

| Event                                  | Payload                               | Dispatched from                       |
| -------------------------------------- | ------------------------------------- | ------------------------------------- |
| `Maintenance\MaintenanceTicketCreated` | `MaintenanceTicket $ticket, ?int $actorId` | `MaintenanceTicketController::store`  |
| `Maintenance\MaintenanceResolved`      | `MaintenanceTicket $ticket, ?int $actorId` | `MaintenanceTicketController::update` |

### Unit

| Event                    | Payload                                                           | Dispatched from                                                                                                                                                                        |
| ------------------------ | ----------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Unit\UnitStatusChanged` | `Unit $unit, UnitStatus $from, UnitStatus $to, ?int $actorId`     | `LeaseController::store`, `LeaseController::moveOut`, `LeaseController::move`, `LeaseController::destroy`, `MaintenanceTicketController::store`, `MaintenanceTicketController::update` |

**Payload: `UnitStatusChanged`**

```php
public readonly Unit $unit;
public readonly UnitStatus $from;   // previous status
public readonly UnitStatus $to;     // new status
public readonly ?int $actorId;      // ID of the acting user, null for system-triggered
```

### Reminder

| Event                                  | Payload                 | Dispatched from                       |
| -------------------------------------- | ----------------------- | ------------------------------------- |
| `Reminder\InvoiceReminderDispatched`   | `ReminderEvent $event`  | `SendRentReminders`, `ForceSendReminder` |

`InvoiceReminderDispatched` fires to trigger a rent reminder notification — one event per reminder log entry created. The actual notification delivery (WhatsApp, mail, etc.) is handled by a listener in `AppServiceProvider`. Plugin consumers can use this event for analytics, tenant-facing notification history, or cross-channel relay, but should note it fires before delivery completes — it is not a delivery confirmation.

### Settings

| Event                      | Payload                                   | Dispatched from                                   |
| -------------------------- | ----------------------------------------- | ------------------------------------------------- |
| `Settings\SettingsUpdated` | `string $group, array $keys, ?int $actorId` | `UpdateSettings` action, `SettingsManager::set()` |

**Payload: `SettingsUpdated`**

```php
public readonly string $group;  // settings group (e.g. 'general', 'reminders') or plugin-defined page key
public readonly array $keys;    // affected setting keys
public readonly ?int $actorId;
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
2. Dispatch it from the Controller after the state change is committed.
3. Document the event in this catalog.
4. Add a test in `tests/Feature/Domain/DomainEventTest.php`.

## Extension Guidance

- Events are the stable plugin API — never change a published event's payload without a major version bump.
- Prefer adding new properties as nullable (backward-compatible) over breaking changes.
- Keep events fired **after** the state change is committed (inside or after the DB transaction, dispatched from Controllers).
