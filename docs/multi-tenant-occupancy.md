# Multi-Tenant Unit Occupancy

> **Status:** Design Document (not yet implemented)
> **Issue:** [OPE-18](https://linear.app/openkos/issue/OPE-18)
> **Decision:** Do NOT implement during MVP. Finish single-tenant leases, payments, and overdue tracking first.

## Context

The kos (boarding house) market commonly requires shared units — multiple tenants occupying a single unit, splitting the rent. The current MVP architecture assumes:

- One active lease per unit
- One tenant per lease
- One tenant per unit

The `units.capacity` column already exists (defaults to `1`) but is unused. This document describes the target architecture for multi-tenant support while preserving the path for future implementation.

## Current State (MVP)

### Schema

```
leases
├── tenant_id (FK → tenants.id, NOT NULL)    ← one tenant only
├── unit_id (FK → units.id, NOT NULL)
├── rent_amount, billing_interval, billing_unit
├── deposit_amount, deposit_paid_at, ...
└── status

units
├── capacity (default 1)                      ← unused
└── status

tenants
├── user_id (optional)
├── name, phone, id_card_number, ...
└── is_active
```

### Application-Level Constraints

- `CreateLease::execute()` — checks unit capacity via `OccupancyCalculator::canAccommodate()`; aborts 422 if adding tenants would exceed capacity
- `LeaseController@move` — same check on target unit via `OccupancyCalculator`
- `TenantController@assignUnit` — same check via `CreateLease`
- Unit listing queries filter out fully-occupied units via `scopeAvailableForAssignment()`
- Unit status set to `occupied` when `count(active_tenants) >= capacity` (in `CreateLease`)

### DB-Level Constraints

There are **no unique constraints** on `leases.unit_id` or `(unit_id, tenant_id)` that would block multi-tenant. The enforcement is purely application-level. This means the DB schema is already forward-compatible.

### Race Condition Risk

The application-level checks are separate queries from the insert (not wrapped in a transaction with `FOR UPDATE` lock). Two concurrent requests could both pass the check and create two active leases on the same unit. Address this when implementing multi-tenant (or earlier if race conditions become a problem).

## Target Architecture

### Recommended Model: Shared Lease (Indonesia-First)

One lease per unit, multiple tenants on that lease. This matches how kos owners think about unit pricing — a unit costs X, not a bed costs X.

```
One Lease = Unit A1 (2jt/month)
  ├── Athhar (primary tenant)
  └── Budi  (co-tenant)
```

### Schema

#### New Pivot Table: `lease_tenant`

```php
Schema::create('lease_tenant', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->boolean('is_primary')->default(false);
    $table->timestamps();

    $table->unique(['lease_id', 'tenant_id']);
});
```

#### Modified `leases` Table

| Column                            | Change                                                |
| --------------------------------- | ----------------------------------------------------- |
| `tenant_id` → `primary_tenant_id` | Rename, make nullable (FK → tenants.id, nullOnDelete) |
|                                   | Add `@after('primary_tenant_id')`                     |

Migration path:

```php
// Step 1: Add the new nullable FK
Schema::table('leases', function (Blueprint $table) {
    $table->foreignId('primary_tenant_id')
        ->nullable()
        ->after('id')
        ->constrained('tenants')
        ->nullOnDelete();
});

// Step 2: Populate lease_tenant pivot from existing data
DB::statement('INSERT INTO lease_tenant (lease_id, tenant_id, is_primary, created_at, updated_at)
    SELECT id, tenant_id, true, NOW(), NOW() FROM leases');

// Step 3: Populate primary_tenant_id from pivot
DB::statement('UPDATE leases SET primary_tenant_id = (
    SELECT tenant_id FROM lease_tenant
    WHERE lease_id = leases.id AND is_primary = true
    LIMIT 1
)');

// Step 4: Drop the old tenant_id column
Schema::table('leases', function (Blueprint $table) {
    $table->dropForeign(['tenant_id']);
    $table->dropColumn('tenant_id');
});
```

#### Updated Model Relationships

```php
// Lease model
public function tenants(): BelongsToMany
{
    return $this->belongsToMany(Tenant::class)
        ->withPivot('is_primary')
        ->withTimestamps();
}

public function primaryTenant(): BelongsTo
{
    return $this->belongsTo(Tenant::class, 'primary_tenant_id');
}

// Tenant model
public function leases(): BelongsToMany
{
    return $this->belongsToMany(Lease::class)
        ->withPivot('is_primary')
        ->withTimestamps();
}
```

## Code Changes Required

This is the exhaustive checklist of every location that needs modification when implementing multi-tenant support.

### Backend

| File                                           | Change                                                                                                                                                                                                             |
| ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `app/Models/Lease.php`                         | Replace `tenant(): BelongsTo` with `tenants(): BelongsToMany`, add `primaryTenant(): BelongsTo`. Remove `tenant_id` from `$fillable`, add `primary_tenant_id`.                                                     |
| `app/Models/Tenant.php`                        | Change `leases(): HasMany` to `leases(): BelongsToMany`.                                                                                                                                                           |
| `app/Http/Requests/StoreLeaseRequest.php`      | Replace `tenant_id` validation with `tenant_ids` (array). Remove `ensureUnitAvailable()`. Add capacity validation (`count(tenant_ids) <= unit.capacity`).                                                          |
| `app/Http/Requests/UpdateLeaseRequest.php`     | Same tenant_id → tenant_ids changes.                                                                                                                                                                               |
| `app/Http/Controllers/LeaseController.php`     | Store: accept `tenant_ids` array, create pivot records. Remove `ensureUnitAvailable()` call. Update `availableUnits` query to check capacity remaining. Move/moveOut: check capacity instead of "no active lease." |
| `app/Http/Controllers/TenantController.php`    | `assignUnit`: accept multiple tenant IDs, check capacity. Update flash messages to plural.                                                                                                                         |
| `app/Http/Controllers/UnitController.php`      | Update `occupied` status logic: check capacity vs active tenant count.                                                                                                                                             |
| All `$lease->tenant` references in controllers | Change to `$lease->tenants` or `$lease->primaryTenant`.                                                                                                                                                            |

### Queries (Affected)

| Query                 | Current                                                      | Future                                                                                                             |
| --------------------- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------ |
| Available units       | `whereDoesntHave('leases', fn => status=active)`             | `withCount(['tenants' => fn => active])` + having `tenants_count < capacity`                                       |
| Unit has active lease | `where('unit_id', $id)->where('status', 'active')->exists()` | `where('unit_id', $id)->where('status', 'active')->withCount('tenants')->having('tenants_count', '<', 'capacity')` |
| Unit display status   | `occupied` if any active lease                               | `occupied` if `tenants_count >= capacity`, `partial` if `0 < tenants_count < capacity`                             |

### Frontend

All changes assume the backend now returns `tenants: TenantInfo[]` instead of `tenant: TenantInfo | null`.

| File                                | Change                                                                                                                                                                               |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `lease-form-sheet.tsx`              | Replace single `SearchableSelect` for tenant with multi-select. Allow selecting up to `unit.capacity` tenants. Change button label to "Assign Tenants." Submit `tenant_ids[]` array. |
| `lease-edit-sheet.tsx`              | Show list of tenants instead of single tenant name. Allow adding/removing tenants.                                                                                                   |
| `lease-detail-sheet.tsx`            | Display tenant list (primary tenant highlighted). Show "Primary" badge on primary tenant.                                                                                            |
| `unit-detail-sheet.tsx`             | Show all tenants currently on active lease, not just `leases[0].tenant`. Show capacity usage (e.g., "2/3 occupied").                                                                 |
| `assign-unit-sheet.tsx`             | Accept single or multiple tenants.                                                                                                                                                   |
| `move-unit-sheet.tsx`               | Handle partial move-out (moving one tenant out while others stay).                                                                                                                   |
| `move-out-sheet.tsx`                | Handle partial move-out.                                                                                                                                                             |
| `properties/units/index.tsx`        | Tenant column: show list of tenant names instead of single. Status column: show "Partial" for partially occupied units.                                                              |
| `properties/units/leases/index.tsx` | Show multi-tenant occupancy on each lease row.                                                                                                                                       |
| `leases/index.tsx`                  | Update tenant display to show multiple names. Update filters.                                                                                                                        |
| `tenants/index.tsx`                 | Update "Active Lease" column to show unit + co-tenants.                                                                                                                              |
| `tenant-detail-sheet.tsx`           | Show co-tenants on shared lease.                                                                                                                                                     |

### TypeScript Types

Update all local type definitions across components/pages:

```typescript
// Before
type Lease = {
    tenant: { id: number; name: string; phone: string | null } | null;
};

// After
type Lease = {
    tenants: Array<{
        id: number;
        name: string;
        phone: string | null;
        pivot: { is_primary: boolean };
    }>;
    primary_tenant: { id: number; name: string; phone: string | null } | null;
};
```

## Business Rules

### Capacity Enforcement

- `count(lease.tenants) <= unit.capacity` at all times
- A unit is "available" when `count(active_tenants) < capacity`
- A unit is "occupied" when `count(active_tenants) >= capacity`
- A unit with `count(active_tenants) > 0 && count(active_tenants) < capacity` is "partially occupied" (new status or display state)

### Primary Tenant

- Each lease has exactly one primary tenant (`lease_tenant.is_primary = true`)
- Primary tenant is the main point of contact
- Only primary tenant can modify lease terms (secondary decisions)
- Primary tenant receives communications, invoices, reminders by default

### Deposit Ownership

- Deposit is stored on the Lease (unit-level), not per tenant
- On full move-out, deposit is refunded to the primary tenant
- On partial move-out, deposit stays with the lease (remaining tenants maintain occupancy)

### Partial Move-Out

- When a tenant leaves but others stay, the lease remains active
- Remove the departing tenant from `lease_tenant` pivot
- No new lease creation — just pivot removal
- Rent amount stays the same (unit-level pricing)

### Full Move-Out / Termination

- When last tenant leaves, terminate the lease (set `status = terminated`, `termination_date`)
- Process deposit refund per standard flow
- Mark unit as `available`

### Partial Move-In

- Adding a tenant to an existing active lease
- Check `count(current_tenants) < capacity`
- Insert new pivot record
- No rent recalculation needed (unit-level pricing)

## Future Considerations

### Payment Splitting

- Currently payments are per-lease (unit-level). Future: optionally split a payment across tenants
- Could be tracked on the pivot table: `lease_tenant.rent_share` (percentage or amount)
- Or remain unit-level: one payment covers the unit, tenants handle their own splits offline

### Overdue Tracking Per Tenant

- Currently overdue tracking is per-lease (one overdue amount per unit)
- Future: could track per-tenant overdue if payment splitting is implemented
- Likely unnecessary: kos owners typically chase the primary tenant for full payment

### Waitlist / Unit Queue

- When a unit is fully occupied, could support a waitlist for tenants wanting to join

### Capacity Changes

- Changing `unit.capacity` when tenants are currently assigned needs careful handling:
    - Increasing capacity: always safe (just allows more tenants)
    - Decreasing capacity below current tenant count: should be blocked or require move-out

### Dashboard / Reporting

- Update occupancy reports to show `occupied_beds / total_beds` instead of `occupied_units / total_units`
- Show partially occupied units as distinct from fully occupied

### Lease Creation from Tenant Side

- Current "Assign to Unit" flow on tenant detail page creates a new lease
- With multi-tenant, this could also be "Add to existing lease" flow for partially occupied units
