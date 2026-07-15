# Rent Reminder System

> **Status:** Implemented (OPE-23)
> **Issue:** [OPE-23](https://linear.app/openkos/issue/OPE-23)
> **Decision:** Layer-first architecture with scheduler → repository → notification pipeline, WhatsApp-only for MVP.

> **Update (OPE-70, [ADR-007](architecture/adr/007-invoice-aggregate.md)):** reminders now read from **payable invoices** instead of the computed `Lease::schedule()`. `PaymentReminderScheduler::pendingFor()` iterates `$lease->invoices()->payable()`, reminder amounts are the invoice **outstanding** (partial-aware), and `ForceSendReminder` picks the first payable invoice. The invoice generator (`invoices:generate`, daily 01:00, 2-month horizon ≥ reminder lookahead) guarantees invoices exist before the 08:00 reminder run. References to `scheduleForReminder()` below are historical.

## Context

The kos management system needs automated rent reminders via WhatsApp to reduce late payments. Requirements:

- Remind tenants before rent is due (upcoming), on the due date (due today), and when overdue
- Derive reminder events from payable invoices on each lease
- Allow manual sending from lease detail sheet as a separate action from automated scheduling
- Support custom message templates per installation
- No payment gateway integration in scope

## Decisions

### 1. Layer-First Architecture (Not Feature-First)

All reminder code lives in domain layers — `Actions/`, `Business/`, `Repositories/`, `Data/`, `Notifications/` — never a top-level `app/Reminders/` feature folder.

**Rationale:** Keeps the architecture predictable. New features add files to existing layers rather than creating new top-level folders that mix concerns.

Initially built with a feature folder (`app/Reminders/`), then refactored into layers during implementation.

### 2. Concurrency Safety: Unique Constraint + Select-Then-Insert

`ReminderRepository::recordIfAbsent()` checks for an existing record first (SELECT), then inserts only if none exists. The unique constraint on `lease_id + period_start + reminder_type + overdue_days` is a safety net — it prevents duplicates from concurrent requests even if the SELECT race window is hit.

**Rationale:** The unique constraint guarantees at most one log per reminder. Error code prefix check (`str_starts_with('23')`) is cross-database compatible (PostgreSQL uses `23505`, SQLite uses `23000`).

### 3. Scheduler Produces Events, Action Orchestrates

`PaymentReminderScheduler` is pure logic — takes a `Lease` and `ReminderSettings`, returns an array of `ReminderEvent` value objects. It knows nothing about persistence or notifications.

`SendRentReminders` Action calls the scheduler, feeds events to `ReminderRepository::recordIfAbsent()`, and dispatches `RentReminder` notification only for events that were successfully recorded (not duplicates).

**Rationale:** Separates concerns — scheduler is testable without DB, repository handles dedup, action orchestrates the pipeline.

### 4. Template Resolution at Send Time

`RentReminder::toWhatsApp()` resolves the custom template from `Setting::get()->reminder_message_template` at the moment of sending, not when the notification is queued.

**Rationale:** Template edits take effect immediately — even for already-queued jobs. Avoids storing a snapshot of the template on the notification object.

### 5. WhatsAppChannel Parameter Typing

`WhatsAppChannel::send()` is typed as `Notification $notifiable` (Laravel convention) with a `@var RentReminder` PHPDoc annotation for Intelephense.

**Rationale:** Laravel's notification system calls `channel->send($notifiable, $notification)` where the second parameter's type is resolved by `$notification`'s return type from `via()`. PHP covariance rules require matching the interface; the docblock provides IDE support without breaking the contract.

### 6. Permission-Based Manual Send

The "Send Reminder" button on lease detail sheet is gated by `Permission::RemindersSend` and `LeasePolicy::sendReminder()` (permission check + property access check).

**Rationale:** Same access control pattern as all other lease actions. The `Gate::before` in `AuthServiceProvider` returns `true` for owner role, but the frontend still needs the permission in `getAllPermissions()` for UI rendering.

### 7. Default Reminder Settings on Setting Model

Reminder configuration (enabled, days_before, overdue_intervals, message_template) lives on the `Setting` singleton model via dedicated columns.

**Rationale:** Single source of truth for now. Per-property override is deferred — when needed, rename columns from `reminder_*` to something else, rather than adding a separate table. Hardcoded `dailyAt('08:00')` for schedule frequency — no `reminder_send_at` column until dynamic scheduling is needed.

### 8. Overdue Interval Comparison

`PaymentReminderScheduler` uses `$overdueDays >= $interval` (not `===`).

**Rationale:** The initial implementation used `>= && < $interval + 1`, which only triggered on the exact matching day. A lease 10 days overdue with intervals `[1, 3, 7]` would never match. The `>=` ensures every interval is checked against the current overdue count.

### 9. CarbonInterface for Type Hints

Scheduler methods accept `CarbonInterface` instead of `Carbon`.

**Rationale:** `now()` returns `Carbon\CarbonImmutable` in test environment. `CarbonInterface` is the common parent of both `Carbon` and `CarbonImmutable`, so scheduler logic works in any environment without explicit conversion.

### 10. LogDriver as Default WhatsApp Driver

`config/services.php` defaults `whatsapp.driver` to `LogDriver::class`. `WhatsAppManager` resolves the driver from config in `AppServiceProvider`.

**Rationale:** Development-safe default — writes to `storage/logs/whatsapp.log` instead of requiring a real WhatsApp connection. The env var `WHATSAPP_DRIVER` lives in `config/services.php` (not `AppServiceProvider`) so omitting it doesn't crash with "Target class [] does not exist."

### 11. Manual Send Goes Through ForceSendReminder Action

`LeaseController::sendReminder()` delegates to `ForceSendReminder` Action, which first tries `PaymentReminderScheduler::pendingFor()` to find already-scheduled but unlogged events. If none exist, it falls back to the first payable invoice and creates a one-off `ReminderEvent`. In either case it records the log via `ReminderRepository::recordIfAbsent()` and dispatches `InvoiceReminderDispatched`. A listener in `AppServiceProvider` picks up that event and performs the actual notification delivery.

**Rationale:** Manual send is a one-off action for the landlord — send one reminder right now, regardless of whether the automated scheduler would have sent one. The scheduled-path fallback ensures consistency: manual send reuses the same event types, dedup, and logging as the automated path. Dispatching `InvoiceReminderDispatched` instead of calling `notifyNow()` directly lets the same listener handle both scheduled and manual sends, keeping channel resolution in one place.

### 12. Primary Tenant Only

Reminders are sent to the lease's primary tenant only (not all tenants on the lease).

**Rationale:** Kos convention — one tenant is responsible for payment. Simplifies implementation for MVP. Easily extended to all tenants later if needed.

## Architecture

```
Schedule (routes/console.php)
  └─ SendRentRemindersCommand
       └─ SendRentReminders (Action)
            ├─ PaymentReminderScheduler (Business)
            │    └─ $lease->invoices()->payable()
            ├─ ReminderRepository::recordIfAbsent()
            └─ InvoiceReminderDispatched::dispatch()
                 └─ AppServiceProvider listener
                      └─ Notification delivery (WhatsApp, mail, etc.)

Manual Send (LeaseController)
  └─ LeaseController::sendReminder()
       └─ ForceSendReminder (Action)
            ├─ PaymentReminderScheduler::pendingFor() (Business)
            │    └─ $lease->invoices()->payable()
            ├─ ReminderRepository::recordIfAbsent()
            └─ InvoiceReminderDispatched::dispatch()
                 └─ AppServiceProvider listener
                      └─ Notification delivery (WhatsApp, mail, etc.)
```

### Directory Layout

```
app/
├── Actions/Reminders/
│   ├── ForceSendReminder.php
│   └── SendRentReminders.php
├── Business/Reminders/
│   └── PaymentReminderScheduler.php
├── Contracts/
│   └── WhatsAppDriver.php
├── Console/Commands/
│   └── SendRentRemindersCommand.php
├── Data/Reminder/
│   ├── ReminderEvent.php
│   └── ReminderSettings.php
├── Enums/
│   └── ReminderType.php
├── Models/
│   └── ReminderLog.php
├── Notifications/
│   ├── Channels/
│   │   └── WhatsAppChannel.php
│   ├── Drivers/
│   │   └── WhatsappLogDriver.php
│   └── RentReminder.php
├── Repositories/
│   └── ReminderRepository.php
└── Services/
    └── WhatsAppManager.php
```

## Future Work

- Per-property reminder overrides (rename `reminder_*` columns or add separate table)
- Dynamic scheduling time (currently hardcoded `dailyAt('08:00')`)
- Email and in-app notification channels
- SMTP config UI + channel selector on settings page
- Baileys driver for real WhatsApp integration
- Optional tenant email field
- Send to all tenants on lease (not just primary)
