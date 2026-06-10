# OpenKOS Architecture

## Overview

OpenKOS is a self-hosted kos (boarding house) management application. It replaces Excel + WhatsApp workflows for kos owners by providing tenant management, room tracking, lease management, and payment tracking.

## Tech Stack

| Layer | Technology | Version |
|---|---|---|
| Backend | Laravel | v13 |
| PHP | PHP | 8.4 |
| Frontend | Inertia.js + React | v3 / v19 |
| Styling | Tailwind CSS | v4 |
| Database | PostgreSQL (primary), SQLite (testing) | - |
| Auth | Laravel Fortify + Spatie Permissions | v1 |
| Testing | Pest | v4 |
| Build | Vite | - |
| TypeScript | TypeScript + ESLint + Prettier | - |
| URL Generation | Laravel Wayfinder | v0 |

## Domain Model

```
User (1)──(N) Property (1)──(N) Room (1)──(N) Lease (1)──(N) Payment
  |                                 |            |
  | (1:1)                          | (N)        | (N)
Tenant                         RoomRate     Tenant
```

### Entities

- **User** — System user with roles (Owner, Admin, Staff). Owners have full access. Linked to a Tenant profile optionally.
- **Property** — A kos building/unit. Owned/managed by Users via a pivot table (`property_user`).
- **Room** — A rentable unit within a Property. Has `capacity`, `status` (available/occupied/maintenance/unavailable), and configurable rates.
- **RoomRate** — Pricing configuration for a Room. Each rate has `billing_interval`, `billing_unit`, `amount`, and `is_active` flag. Supports multiple concurrent rates (e.g., monthly + yearly).
- **Tenant** — A person renting a room. Has contact info, emergency contact, and an optional link to a User account.
- **Lease** — An occupancy agreement for a Room. Currently one Tenant per Lease (MVP). Stores `rent_amount` denormalized for price history, `billing_interval`/`billing_unit` for flexible billing, and deposit tracking.
- **Payment** — A payment made against a Lease. Has `amount`, `period_start`, `period_end`, and `status`.

## Key Architecture Decisions

### Billing Flexibility via RoomRates

Rooms do not have a single `base_price`. Instead, pricing is stored in the `room_rates` table, allowing multiple billing configurations (e.g., monthly 2jt, yearly 20jt). Leases reference a `room_rate_id` and denormalize `rent_amount` for historical accuracy.

### Room-Level Pricing

The kos market prices rooms, not beds. Rent is per room, not per tenant. This aligns with the "Shared Lease" model where multiple tenants share a room and split the single room rent. Payment splitting is a future concern.

### Single-Tenant Leases (MVP)

Initially, each Lease has exactly one Tenant via `leases.tenant_id`. This is enforced at the application level, not the database level. The DB schema has no unique constraints preventing multiple active leases per room, preserving the path for future multi-tenant support (see [docs/multi-tenant-occupancy.md](docs/multi-tenant-occupancy.md)).

### PostgreSQL Compatibility

The primary database is PostgreSQL (Neon serverless). Boolean comparisons use `DB::raw('true')`/`DB::raw('false')` and `whereRaw('is_active is true')` instead of PHP booleans to ensure cross-database compatibility (SQLite for testing).

### Flexible Billing on Leases

Each Lease independently stores `billing_interval` + `billing_unit` + `rent_amount`, with computed `monthly_equivalent` and `billing_label` accessors. This supports daily, weekly, monthly, and yearly billing without enums or complex calendar logic.

## Directory Structure

```
app/
├── Actions/           # Fortify authentication actions
├── Console/           # Artisan commands (app:install, seeds)
├── Enums/             # BillingUnit, RoomStatus, Role
├── Http/
│   ├── Controllers/   # Property, Room, Lease, Tenant, Payment, Dashboard controllers
│   ├── Requests/      # Form requests with validation
│   └── Middleware/     # Custom middleware
├── Models/            # Eloquent models
├── Providers/         # Service providers
└── Services/          # RentScheduleService
database/
├── factories/         # Model factories
├── migrations/        # Schema migrations
└── seeders/           # Owner, Property, Room, Tenant, Lease seeders
resources/js/
├── components/        # Reusable React components (sheets, tables, inputs)
├── layouts/           # App layout
├── pages/             # Inertia page components
├── types/             # Shared TypeScript types (auth, navigation)
└── lib/               # Utility functions
routes/
├── web.php            # Web routes (Inertia)
└── channels.php       # Broadcasting channels
tests/
├── Feature/           # Feature tests
├── Unit/              # Unit tests
└── Pest.php           # Pest configuration
```

## Data Flow

```
User Action → Inertia Visit → Laravel Route → Controller
  → Authorization (Spatie permissions + policies)
  → Form Request Validation
  → Eloquent Query / Mutations
  → Inertia::render() / redirect()
  → React Component Renders State
```

All page navigation and form submissions go through Inertia. There are no Blade views for application pages (only auth views from Fortify). The SPA is fully client-side rendered with server-side data via Inertia props.

## Roles & Authorization

| Role | Access |
|---|---|
| Owner | Full access to all features, all properties |
| Admin | Operational access (properties, rooms, tenants, reports) |
| Staff | Limited access (tenants, dashboard) |

Property-level access is enforced via `PropertyUser` pivot. Owners bypass this check.

## Known Future Architecture

- **Multi-Tenant Occupancy** — See [docs/multi-tenant-occupancy.md](docs/multi-tenant-occupancy.md) for the planned migration from single-tenant to multi-tenant leases via a `lease_tenant` pivot table.
- **Payment Splitting** — Future support for splitting a single room's rent across multiple occupants.
- **Deposit Per Tenant** — Moving deposit tracking from Lease-level to Tenant-level for shared occupancy.
