# Multi-Tenant Unit Occupancy

> **Status:** Implemented
> **Issue:** [OPE-18](https://linear.app/openkos/issue/OPE-18)

## Context

The kos (boarding house) market commonly requires shared units — multiple tenants occupying a single unit, splitting the rent.

## Implementation

### Model: Shared Lease (Indonesia-First)

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
