# Multi-Tenant Room Occupancy

> **Status:** Design Document (not yet implemented)
> **Issue:** [OPE-18](https://linear.app/openkos/issue/OPE-18)
> **Decision:** Do NOT implement during MVP. Finish single-tenant leases, payments, and overdue tracking first.

## Context

The kos (boarding house) market commonly requires shared rooms — multiple tenants occupying a single room, splitting the rent. The current MVP architecture assumes:

- One active lease per room
- One tenant per lease
- One tenant per room

The `rooms.capacity` column already exists (defaults to `1`) but is unused. This document describes the target architecture for multi-tenant support while preserving the path for future implementation.

## Current State (MVP)

### Schema

```
leases
├── tenant_id (FK → tenants.id, NOT NULL)    ← one tenant only
├── room_id (FK → rooms.id, NOT NULL)
├── rent_amount, billing_interval, billing_unit
├── deposit_amount, deposit_paid_at, ...
└── status

rooms
├── capacity (default 1)                      ← unused
└── status

tenants
├── user_id (optional)
├── name, phone, id_card_number, ...
└── is_active
```

### Application-Level Constraints

- `StoreLeaseRequest::ensureRoomAvailable()` — aborts 422 if room has any active lease
- `LeaseController@move` — same check on target room
- `TenantController@assignRoom` — same check
- Room listing queries filter out rooms with active leases via `whereDoesntHave('leases', fn($q) => $q->where('status', 'active'))`
- Room status set to `occupied` when an active lease exists (in `RoomController`)

### DB-Level Constraints

There are **no unique constraints** on `leases.room_id` or `(room_id, tenant_id)` that would block multi-tenant. The enforcement is purely application-level. This means the DB schema is already forward-compatible.

### Race Condition Risk

The application-level checks are separate queries from the insert (not wrapped in a transaction with `FOR UPDATE` lock). Two concurrent requests could both pass the check and create two active leases on the same room. Address this when implementing multi-tenant (or earlier if race conditions become a problem).

## Target Architecture

### Recommended Model: Shared Lease (Indonesia-First)

One lease per room, multiple tenants on that lease. This matches how kos owners think about room pricing — a room costs X, not a bed costs X.

```
One Lease = Room A1 (2jt/month)
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

| Column | Change |
|---|---|
| `tenant_id` → `primary_tenant_id` | Rename, make nullable (FK → tenants.id, nullOnDelete) |
| | Add `@after('primary_tenant_id')` |

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

| File | Change |
|---|---|
| `app/Models/Lease.php` | Replace `tenant(): BelongsTo` with `tenants(): BelongsToMany`, add `primaryTenant(): BelongsTo`. Remove `tenant_id` from `$fillable`, add `primary_tenant_id`. |
| `app/Models/Tenant.php` | Change `leases(): HasMany` to `leases(): BelongsToMany`. |
| `app/Http/Requests/StoreLeaseRequest.php` | Replace `tenant_id` validation with `tenant_ids` (array). Remove `ensureRoomAvailable()`. Add capacity validation (`count(tenant_ids) <= room.capacity`). |
| `app/Http/Requests/UpdateLeaseRequest.php` | Same tenant_id → tenant_ids changes. |
| `app/Http/Controllers/LeaseController.php` | Store: accept `tenant_ids` array, create pivot records. Remove `ensureRoomAvailable()` call. Update `availableRooms` query to check capacity remaining. Move/moveOut: check capacity instead of "no active lease." |
| `app/Http/Controllers/TenantController.php` | `assignRoom`: accept multiple tenant IDs, check capacity. Update flash messages to plural. |
| `app/Http/Controllers/RoomController.php` | Update `occupied` status logic: check capacity vs active tenant count. |
| All `$lease->tenant` references in controllers | Change to `$lease->tenants` or `$lease->primaryTenant`. |

### Queries (Affected)

| Query | Current | Future |
|---|---|---|
| Available rooms | `whereDoesntHave('leases', fn => status=active)` | `withCount(['tenants' => fn => active])` + having `tenants_count < capacity` |
| Room has active lease | `where('room_id', $id)->where('status', 'active')->exists()` | `where('room_id', $id)->where('status', 'active')->withCount('tenants')->having('tenants_count', '<', 'capacity')` |
| Room display status | `occupied` if any active lease | `occupied` if `tenants_count >= capacity`, `partial` if `0 < tenants_count < capacity` |

### Frontend

All changes assume the backend now returns `tenants: TenantInfo[]` instead of `tenant: TenantInfo | null`.

| File | Change |
|---|---|
| `lease-form-sheet.tsx` | Replace single `SearchableSelect` for tenant with multi-select. Allow selecting up to `room.capacity` tenants. Change button label to "Assign Tenants." Submit `tenant_ids[]` array. |
| `lease-edit-sheet.tsx` | Show list of tenants instead of single tenant name. Allow adding/removing tenants. |
| `lease-detail-sheet.tsx` | Display tenant list (primary tenant highlighted). Show "Primary" badge on primary tenant. |
| `room-detail-sheet.tsx` | Show all tenants currently on active lease, not just `leases[0].tenant`. Show capacity usage (e.g., "2/3 occupied"). |
| `assign-room-sheet.tsx` | Accept single or multiple tenants. |
| `move-room-sheet.tsx` | Handle partial move-out (moving one tenant out while others stay). |
| `move-out-sheet.tsx` | Handle partial move-out. |
| `properties/rooms/index.tsx` | Tenant column: show list of tenant names instead of single. Status column: show "Partial" for partially occupied rooms. |
| `properties/rooms/leases/index.tsx` | Show multi-tenant occupancy on each lease row. |
| `leases/index.tsx` | Update tenant display to show multiple names. Update filters. |
| `tenants/index.tsx` | Update "Active Lease" column to show room + co-tenants. |
| `tenant-detail-sheet.tsx` | Show co-tenants on shared lease. |

### TypeScript Types

Update all local type definitions across components/pages:

```typescript
// Before
type Lease = {
    tenant: { id: number; name: string; phone: string | null } | null;
};

// After
type Lease = {
    tenants: Array<{ id: number; name: string; phone: string | null; pivot: { is_primary: boolean } }>;
    primary_tenant: { id: number; name: string; phone: string | null } | null;
};
```

## Business Rules

### Capacity Enforcement

- `count(lease.tenants) <= room.capacity` at all times
- A room is "available" when `count(active_tenants) < capacity`
- A room is "occupied" when `count(active_tenants) >= capacity`
- A room with `count(active_tenants) > 0 && count(active_tenants) < capacity` is "partially occupied" (new status or display state)

### Primary Tenant

- Each lease has exactly one primary tenant (`lease_tenant.is_primary = true`)
- Primary tenant is the main point of contact
- Only primary tenant can modify lease terms (secondary decisions)
- Primary tenant receives communications, invoices, reminders by default

### Deposit Ownership

- Deposit is stored on the Lease (room-level), not per tenant
- On full move-out, deposit is refunded to the primary tenant
- On partial move-out, deposit stays with the lease (remaining tenants maintain occupancy)

### Partial Move-Out

- When a tenant leaves but others stay, the lease remains active
- Remove the departing tenant from `lease_tenant` pivot
- No new lease creation — just pivot removal
- Rent amount stays the same (room-level pricing)

### Full Move-Out / Termination

- When last tenant leaves, terminate the lease (set `status = terminated`, `termination_date`)
- Process deposit refund per standard flow
- Mark room as `available`

### Partial Move-In

- Adding a tenant to an existing active lease
- Check `count(current_tenants) < capacity`
- Insert new pivot record
- No rent recalculation needed (room-level pricing)

## Future Considerations

### Payment Splitting

- Currently payments are per-lease (room-level). Future: optionally split a payment across tenants
- Could be tracked on the pivot table: `lease_tenant.rent_share` (percentage or amount)
- Or remain room-level: one payment covers the room, tenants handle their own splits offline

### Overdue Tracking Per Tenant

- Currently overdue tracking is per-lease (one overdue amount per room)
- Future: could track per-tenant overdue if payment splitting is implemented
- Likely unnecessary: kos owners typically chase the primary tenant for full payment

### Waitlist / Room Queue

- When a room is fully occupied, could support a waitlist for tenants wanting to join

### Capacity Changes

- Changing `room.capacity` when tenants are currently assigned needs careful handling:
  - Increasing capacity: always safe (just allows more tenants)
  - Decreasing capacity below current tenant count: should be blocked or require move-out

### Dashboard / Reporting

- Update occupancy reports to show `occupied_beds / total_beds` instead of `occupied_rooms / total_rooms`
- Show partially occupied rooms as distinct from fully occupied

### Lease Creation from Tenant Side

- Current "Assign to Room" flow on tenant detail page creates a new lease
- With multi-tenant, this could also be "Add to existing lease" flow for partially occupied rooms
