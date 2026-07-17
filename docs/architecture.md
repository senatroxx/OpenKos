# Architecture

> **Status:** Living document
> **Purpose:** Describe the application's architectural patterns, layer conventions, and data flow. Decisions with trade-offs are recorded as ADRs in [`docs/architecture/adr/`](architecture/adr/README.md); detailed feature designs live in their own docs (see `docs/multi-tenant-occupancy.md`, `docs/rent-reminders.md`).

## Stack

| Layer         | Technology                                             |
| ------------- | ------------------------------------------------------ |
| Backend       | PHP 8.4, Laravel 13                                    |
| Frontend      | React 19, Inertia 3, TypeScript                        |
| UI Kit        | shadcn/ui, Tailwind CSS 4                              |
| Auth          | Laravel Fortify (login, 2FA, passkeys, password reset) |
| Authorization | Spatie Permission (RBAC)                               |
| Routes (TS)   | Laravel Wayfinder                                      |
| Database      | PostgreSQL (dev: SQLite)                               |
| Queue         | Database driver                                        |
| Testing       | Pest 4                                                 |

## Layer Architecture

The application uses a **multi-layered domain-oriented** architecture. Files are organized by architectural concern (layer-first), not by feature.

Application code lives in `app/` (`App\` namespace). The extensibility platform lives in `src/` (`OpenKOS\` namespace) — registries, the `OpenKOS` facade/manager, and the plugin system. See `docs/platform.md`.

```
app/
├── Actions/          Single-responsibility operations (orchestration)
├── Business/         Pure domain logic, business rules
├── Data/             Typed DTOs / value objects
├── Database/         PostgreSQL driver extensions
├── Results/          Typed result objects from action/business operations
├── Repositories/     Query abstraction over Eloquent
├── Models/           Eloquent models
├── Notifications/    Notification classes + custom channels + drivers
├── Contracts/        Interfaces
├── Services/         External integrations
├── Enums/            Type-safe domain constants
├── Events/           Domain event classes
├── Listeners/        Event subscribers and listeners
├── Http/             Controllers, Middleware, Form Requests
├── Policies/         Authorization per model
├── Concerns/         Reusable traits
├── Console/          Artisan commands
├── Exceptions/       Custom exception classes
├── Providers/        Service providers
├── Support/          Utility classes
└── Tables/           Reusable table/filter/column builders

src/
├── Core/Contracts/   Platform-facing interfaces (NotificationDriver, PaymentGateway, PluginDiscovery)
├── Platform/         Registries, OpenKOSManager, OpenKOS facade, Plugin base class
└── Plugins/          Plugin implementations (config/platform.php lists enabled plugins)
```

### Layer Rules

**Controllers** (`Http/Controllers/`) — Handle HTTP, validate requests (via Form Requests), authorize (via Policies), delegate to Actions, return responses (Inertia or redirect). Never contain business logic. Max one `try/catch` at the controller boundary for integration-level errors.

**Redirect convention** — Mutations (`store`, `update`, and sheet-triggered actions like `assign`, `moveOut`, `renew`) return **`back()`**, not `to_route('…index')`. The UI is sheet-driven: a form opens as a `<Sheet>` over a list _or_ a detail page and closes client-side on success, so `back()` keeps the user on whichever page they triggered it from, with fresh data, and avoids hardcoding a destination. Inertia converts the `PUT`/`PATCH`/`DELETE` redirect to 303 automatically. Two exceptions stay explicit: **`destroy`** returns `to_route('…index')` (redirecting to the just-deleted record's page would 404), and auth/cross-context flows (e.g. `completeInvitation` → `login`) target their real destination. When testing a `back()` action, set `->from(route(...))` on the request so the assertion has a referer to land on.

**Form Requests** (`Http/Requests/`) — Per-endpoint validation rules and `authorize()` checks that don't require model instance access. Keep validation rules here, not in the controller.

**Actions** (`Actions/`) — Single-responsibility operations that orchestrate multiple steps. Typically: call Business layer → call Repository → dispatch notification. Actions are injectable classes (not static). Constructor-inject dependencies.

**Business** (`Business/`) — Pure domain logic with no side effects. Takes primitives or models as input, returns primitives or DTOs. No database calls, no notifications, no framework dependencies beyond Carbon. Stateless.

**Data** (`Data/`) — Immutable value objects / DTOs. Typed constructors, `readonly` properties. Used as input/output contracts between layers. Include static factory methods (`fromSetting()`, `fromRequest()`) where useful.

**Results** (`Results/`) — Typed result objects that carry success data or an error message. Used when an operation can fail in expected ways. Named: `*Result` with `succeeded()`/`failed()` helpers.

**Repositories** (`Repositories/`) — Thin wrappers over Eloquent for queries that involve dedup, complex filtering, or need to be mockable for tests. One class per aggregate.

**Services** (`Services/`) — Classes that wrap external integrations (messaging APIs, file storage, etc.). Delegated to by other layers — never called from controllers directly. Injectable, testable via contracts.

**Notifications** (`Notifications/`) — Notification classes (implement `ShouldQueue`), custom `Channels/`, and pluggable `Drivers/` for transport abstraction. Channel accepts a notifiable and the notification; driver performs the actual delivery.

### Data Flow Pattern

```
HTTP Request
  → Controller (validates, authorizes)
    → Action (orchestrates)
      → Business (domain logic, returns DTOs)
      → Repository (data access, returns models)
      → Notification (dispatches)
```

### Domain Modules (Vertical Slices)

Two domains follow a consistent vertical slice through the layers:

```
Leases:
  Actions/Leases/RenewLease.php
  Business/Leases/RenewalEligibilityChecker.php
  Business/Leases/LeaseFinancialChecker.php
  Data/Lease/RenewLeaseData.php
  Results/Lease/RenewLeaseResult.php

Reminders:
  Actions/Reminders/SendRentReminders.php
  Business/Reminders/PaymentReminderScheduler.php
  Data/Reminder/ReminderEvent.php
  Data/Reminder/ReminderSettings.php
  Repositories/ReminderRepository.php
```

New domains should follow the same pattern: pick the layers you need, place files in `{Layer}/{Domain}/`.

## Domain Model

### Core Entities

```
Property (has a type — see below)
  ├── Regions / Cities (location)
  └── Units
       ├── UnitRates (pricing history)
       ├── LeaseUnitHistory (unit transfer records)
       ├── MaintenanceTickets
       └── Leases
            ├── Tenants (pivot: lease_tenant)
            ├── Invoices
            │    ├── InvoiceLineItems
            │    └── Payments
            │         ├── PaymentAllocations (M:N pivot: payment_id, invoice_id, amount)
            │         └── PaymentProofs
            └── ReminderLogs

Tenant
  ├── Documents
  └── Leases (pivot: lease_tenant)

User (staff / owner)
  ├── Properties (pivot: property_user)
  └── Roles / Permissions

ActivityLog (records user-triggered activity across entities)
AuditLog (records setting changes and sensitive operations)
```

### Property Type

Every property has a `type` (`properties.type`), backed by the `App\Models\PropertyType` Eloquent model: `boarding_house` (default), `apartment`, `villa`, `hostel`, `hotel`. The column defaults to `boarding_house`, so existing properties and any created without an explicit type are boarding houses. The model is a user-managed CRUD resource (`Settings\PropertyTypeController`) with `slug`, `label`, `is_active`, and `sort_order` columns. Property types exist so future business logic can branch on the type instead of assuming every property is a kos — no behaviour differs by type yet; it's the domain seam for that divergence.

### Multi-Tenant Occupancy

A unit can hold multiple tenants on a single lease via the `lease_tenant` pivot table. Each lease has one `primary_tenant_id` (the main point of contact). `units.capacity` limits the total occupants. See `docs/multi-tenant-occupancy.md` for the full design.

### Payments & Rent Schedule

Payments settle invoices — `payments` has an `invoice_id` FK (not polymorphic). The rent schedule is generated dynamically by `Lease::schedule()` — it is not stored. The schedule engine feeds `invoices:generate` (daily 01:00, 2-month horizon), which materializes `Invoice` records with `total`, `amount_paid`, `status`, `period_start`, and `period_end`. Payments are recorded against a specific invoice via `RecordPayment`, and `Invoice::recalculateStatus()` recomputes status (Pending, Partial, Paid) from confirmed payment totals. See `docs/architecture/adr/007-invoice-aggregate.md`.

### Reminders

Reminders are scheduled by `SendRentRemindersCommand` (daily at 08:00), which calls `SendRentReminders` Action. The Action iterates active leases, calls `PaymentReminderScheduler` to generate events, records them in `reminder_logs` (deduped via unique constraint), and dispatches `RentReminder` via WhatsApp. Manual sending from the lease detail sheet bypasses the scheduler — sends one reminder immediately via `notifyNow()`. See `docs/rent-reminders.md`.

## Authentication & Authorization

**Fortify** handles the auth backend (login, registration, password reset, 2FA, passkeys). The app is an Inertia SPA — Fortify actions are configured but the frontend renders React pages.

**Authorization** uses Spatie Permission. A `Gate::before` in `AuthServiceProvider` fully bypasses all policy checks for users with the `owner` role. Non-owner users are checked against individual permissions and policies. Every model has a corresponding Policy class. Frontend permission checks reference a seeded `getAllPermissions()` list.

### Permission Convention

Permissions are defined as PHP enums (`App\Enums\Permission`) with:

- A `case` name (e.g., `RemindersSend`)
- A `label()` method returning a human-readable name
- A `description()` method for the admin UI

Route authorization is done at the controller level via `$this->authorize()` with Policy methods. The frontend gates UI elements by checking the permission in the user's session data.

## Frontend Architecture

Component-level UI conventions — form handling (`useForm`), sheet/dialog patterns (reset-on-close, remount keys, footer layout), and the shared building blocks to reuse — are documented in [`docs/frontend-conventions.md`](frontend-conventions.md).

### Directory Structure

```
resources/js/
├── pages/           Inertia page components (one per route)
├── components/
│   ├── features/    Domain-specific components grouped by domain
│   ├── shared/      Reusable app-level components
│   └── ui/          shadcn/ui primitives
├── layouts/         Layout components (app, auth, settings)
├── hooks/           Custom React hooks
├── lib/             Utility functions
├── types/           TypeScript type definitions
├── plugins/         Plugin frontend entry points (side-effect imports)
├── actions/         Wayfinder-generated TS functions for controllers
└── routes/          Wayfinder-generated TS functions for named routes
```

### Conventions

- **Pages** go in `pages/` matching the route structure. These are what `Inertia::render()` references.
- **Feature components** go in `components/features/{domain}/`. Reused across multiple pages within a domain.
- **UI primitives** from shadcn/ui live in `components/ui/`. These are generic, unstyled components (dialog, sheet, button, etc.).
- **Layouts** wrap page components. Settings has its own layout with sidebar navigation.
- **Wayfinder** generates typed TS functions — always import from `@/actions/` or `@/routes/` instead of hardcoding URLs.
- **Types** are defined manually in `types/` (no automatic generation from PHP models). Match the JSON shape returned by Inertia.

## Route Structure

```
routes/
├── web.php          — Main routes (Inertia pages, API-like POST endpoints)
├── settings.php     — Settings panel (included from web.php)
└── console.php      — Artisan schedule definitions
```

Named routes follow the Laravel resource convention (`{plural}.{action}`). Settings routes are namespaced under `settings.`. Scheduled commands are defined in `console.php` using the `Schedule` facade.

## Domain State Machines

Status fields on core entities are backed by string enums with model casts and centralized transition validators that prevent invalid state changes.

### Lease Lifecycle

```
Active ──→ Terminated   (destroy, moveOut)
Active ──→ Renewed      (renewal)
```

- **Enum:** `App\Enums\LeaseStatus` (Active, Expired, Terminated, Renewed)
- **Validator:** `App\Business\Leases\LeaseStatusValidator`
- **Cast on** `Lease.status`
- `Expired` is reserved for future use (no code path sets it yet).
- Terminal states: `Terminated`, `Renewed`.

### Payment Lifecycle

```
Pending ──→ Confirmed   (verify: confirm)
Pending ──→ Cancelled   (verify: reject)
```

Created as `Confirmed` (auto-verify or no proof) or `Pending` (proof uploaded, cannot auto-verify).

- **Enum:** `App\Enums\PaymentStatus` (Pending, Confirmed, Cancelled)
- **Validator:** `App\Business\Payments\PaymentStatusValidator`
- **Cast on** `Payment.status`

### Maintenance Ticket Lifecycle

```
Reported ──→ InProgress ──→ Resolved
    │             │
    └──→ Cancelled └──→ Cancelled
```

- **Enum:** `App\Enums\MaintenanceStatus` (Reported, InProgress, Resolved, Cancelled)
- **Validator:** `App\Business\Maintenance\TransitionValidator`

### Unit Lifecycle (Programmatic Only)

Programmatic transitions are enforced by inline guard checks in the Actions (e.g., `abort_if` before leasing a maintenance or unavailable unit, `scopeAvailableForAssignment` excluding both states). The `UnitStatusValidator` class documents the canonical set of allowed transitions but is not wired into every Action. Manual edits via the unit form bypass all validation (admin can set any status).

```
Available  ──→ Occupied       (lease created)
Available  ──→ Maintenance     (ticket blocks empty unit)
Occupied   ──→ Maintenance     (ticket blocks occupied unit)
Maintenance ──→ Available      (ticket resolved, no active lease)
Maintenance ──→ Occupied       (ticket resolved, active lease exists)
Occupied   ──→ Available       (all leases terminated)
```

- **Enum:** `App\Enums\UnitStatus` (Available, Occupied, Maintenance, Unavailable)
- **Validator:** `App\Business\Units\UnitStatusValidator`
- **Cast on** `Unit.status`
- `Unavailable` is a manual-only state — no programmatic transition targets it.

## Database Conventions

- Migrations include a descriptive name (not just `create_*_table`).
- Timestamps use `nullableTimestamps()` where appropriate (pivot tables).
- Foreign keys use `constrained()` with explicit `cascadeOnDelete` / `nullOnDelete` / `restrictOnDelete`.
- Unique constraints cover business-unique combinations (e.g., `lease_id + period_start + reminder_type + overdue_days` on `reminder_logs`).
- Currency values are stored as `decimal(12, 2)`. The frontend formats as-is for display.
- The `settings` table is a singleton — one row with columns for each setting group. No key-value pattern.

### Foreign Key Delete Strategy

Every foreign key intentionally chooses one of four strategies:

| Strategy           | When to use                                                                                                        |
| ------------------ | ------------------------------------------------------------------------------------------------------------------ |
| `restrictOnDelete` | Parent references a historical business record. Prevents hard-deleting the parent while children exist.            |
| `nullOnDelete`     | The child can logically exist without the parent (nullable audit trails, optional associations).                   |
| `cascadeOnDelete`  | The child is meaningless without the parent (auth credentials, dependent files), or is a framework table (Spatie). |
| `noActionOnDelete` | Reserved for transactional integrity (not currently used).                                                         |

**Historical business records** (leases, payments, maintenance tickets, reminders, documents, pricing history, audit trails) must use `restrictOnDelete` on their parent FK. This prevents hard-deleting a parent model while its business records still reference it. Soft-deletable parents (Property, Unit, Lease, Tenant) use `$model->delete()` which sets `deleted_at` — the FK constraint only fires on `forceDelete()` or raw SQL deletes. Child records themselves may be hard-deleted (e.g. MaintenanceTicket, TenantDocument) — the constraint protects the parent, not the child.

**User audit references** (created_by, confirmed_by, recorded_by, etc.) use `nullOnDelete` — the record survives even if the user is hard-deleted.

**Framework pivot tables** (Spatie role/permission assignments, property_user) use `cascadeOnDelete` — they are ephemeral metadata. Domain pivot tables linking business records (e.g. `lease_tenant`) follow the same strategy as the business records they connect.

The full FK inventory with per-constraint rationale is enforced by `tests/Feature/Schema/ForeignKeyDeleteStrategyTest.php`.

### Composite Indexes

Composite indexes are added to support production query patterns (filtering and sorting across multiple columns). The rationale for each:

| Table                  | Index                                     | Columns                                | Query pattern                                                                                |
| ---------------------- | ----------------------------------------- | -------------------------------------- | -------------------------------------------------------------------------------------------- |
| `leases`               | `idx_leases_status_start_date`            | `status, start_date`                   | Dashboard base query `where(status='active', start_date <= now)`, overdue lease calculation. |
| `maintenance_tickets`  | `idx_maintenance_tickets_property_status` | `property_id, status`                  | Property-scoped ticket filtering.                                                            |
| `maintenance_tickets`  | `idx_maintenance_tickets_unit_status`     | `unit_id, status`                      | Unit-scoped maintenance history queries.                                                     |
| `units`                | `idx_units_property_status`               | `property_id, status`                  | Unit availability listings (`scopeAvailableForAssignment`).                                  |
| `lease_unit_histories` | `idx_luh_from_reason_date`                | `from_unit_id, reason, effective_date` | Maintenance transfer lookups — equality on first 2 columns + ORDER BY on third.              |
| `unit_rates`           | `idx_unit_rates_unit_active_interval`     | `unit_id, is_active, billing_interval` | `activeRates()` relation.                                                                    |
| `properties`           | `idx_properties_active_name`              | `is_active, name`                      | Admin property dropdowns sorted by name.                                                     |

**Design decisions:**

- Existing single-column indexes (`status`, `priority`) are preserved — they serve queries that filter on those columns alone without additional columns (e.g., global status filter on maintenance tickets).
- The set of composite indexes is enforced by `tests/Feature/Schema/CompositeIndexTest.php`.

## Testing

- Pest 4 for both Feature and Unit tests.
- Feature tests test the full stack: HTTP request → controller → action → DB → response.
- Factory states for common test scenarios.
- `RefreshDatabase` trait for database isolation.
- Test files mirror the source structure: `tests/Feature/Settings/ReminderSettingsTest.php` for `app/Http/Controllers/Settings/ReminderController.php`.
- Run with `php artisan test --compact`.
- Frontend tests: none currently (end-to-end testing deferred).

## Infrastructure

- Queue driver is `database` — jobs are stored in the `jobs` table. Queue worker must be running for async notifications.
- WhatsApp driver is resolved from the platform `NotificationRegistry` (seeded by `config/services.php`). Default is `WhatsappLogDriver` (writes to `storage/logs/whatsapp.log`).
- `Date` facade uses `CarbonImmutable` (configured in `AppServiceProvider`). All date type hints use `CarbonInterface` to accept both `Carbon` and `CarbonImmutable`.
- Prohibits destructive DB commands (`DB::prohibitDestructiveCommands()`) in production.
- Password defaults are strict in production (12 chars, mixed case, symbols, uncompromised).
