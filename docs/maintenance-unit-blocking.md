# Maintenance Unit Blocking

> **Status:** Implemented
> **Issue:** [OPE-35](https://linear.app/openkos/issue/OPE-35)

## Overview

When a maintenance ticket is created for a unit, the admin can optionally block the unit for maintenance. A blocked unit cannot receive new leases, assignments, or tenant moves. The unit is restored when the ticket is resolved and the admin opts to restore availability.

## Unit Status Model

The `units.status` column holds one of four values:

| Status | Meaning |
|--------|---------|
| `available` | Empty unit, ready for lease |
| `occupied` | Unit has an active lease |
| `maintenance` | Unit is blocked for maintenance |
| `unavailable` | Unit is blocked for other reasons (manual) |

## Blocking Flow

1. Admin creates a maintenance ticket with a unit selected
2. Checks "Block unit for maintenance" checkbox
3. If unit is **empty** → unit status set to `maintenance` immediately
4. If unit is **occupied** → a dialog appears with options:
   - **Move tenant to another unit**: selects a target unit, creates the ticket, terminates the old lease, creates a new lease on the target unit, and blocks the original unit. The target unit is set to `occupied`.
   - **Keep tenant, just mark as maintenance**: creates the ticket, sets unit to `maintenance`. The tenant stays with their active lease but the unit won't appear in available-unit dropdowns.

## Enforcement

### Lease Creation
`LeaseController::store()` aborts with 422 if unit status is `maintenance`.

### Tenant Assignment
`TenantController::assignUnit()` aborts with 422 if unit status is `maintenance`.

### Lease Move
`LeaseController::move()` aborts with 422 if target unit status is `maintenance`.

`LeaseController::moveOut()` aborts with 422 if target unit status is `maintenance` (when `move_to_another_unit` is selected).

### Available Unit Queries
All four "available units" queries now exclude maintenance units:

- `LeaseController::index()` — unit-specific lease page
- `LeaseController::globalIndex()` — global leases page
- `TenantController::index()` — tenant list
- `UnitController::index()` — unit list

Filter: `->whereNotIn('status', ['maintenance'])`

### Status Protection
When a lease is terminated (`destroy()`, `move()`, `moveOut()`), the unit is only set back to `Available` if its current status is not `maintenance`. This prevents accidentally overriding a blocked unit.

## Restoring a Unit

When editing a ticket (status change via the Resolve or Cancel action), if the ticket's unit is in maintenance, a "Restore unit availability" checkbox appears.

- **Checked + no other open tickets for this unit**: unit status set to `Available` (if no active lease) or `Occupied` (if active lease exists)
- **Checked + other open tickets exist**: unit stays in `maintenance` (flash message explains why)
- **Unchecked**: unit stays in `maintenance`

## Technical Details

### Request Fields

**StoreMaintenanceTicketRequest:**
- `block_unit` — boolean, optional. When true, sets the selected unit's status to `maintenance`.
- `move_tenant_to_unit_id` — integer, optional. When provided with `block_unit`, moves the active lease from the blocked unit to the target unit before blocking.

**UpdateMaintenanceTicketRequest:**
- `restore_unit` — boolean, optional. When true on status transition to Resolved/Cancelled, restores the unit's availability if no other open tickets exist.

### Controller Logic

`MaintenanceTicketController::blockUnit()` handles the blocking logic:
1. Locks the unit with `lockForUpdate()`
2. Finds active lease if any
3. If active lease + move target:
   - Locks target unit with `lockForUpdate()`
   - Validates target is not under maintenance, not same unit, no active lease
   - Validates capacity
   - **Transfers the lease**: updates `lease.unit_id` to target unit, appends transfer note
   - Records `LeaseUnitHistory` entry (from_unit, to_unit, reason=maintenance, transferred_by)
   - Sets original unit to `maintenance`, target to `occupied`
   - Lease agreement remains intact — only the physical unit changes
4. If active lease + no move: sets unit to `maintenance` with warning
5. If no active lease: sets unit to `maintenance`

### Unit Transfer History

A `lease_unit_histories` table tracks every unit change within a lease:

| Column | Type | Description |
|--------|------|-------------|
| `lease_id` | FK | The lease being transferred |
| `from_unit_id` | FK | Original unit |
| `to_unit_id` | FK | Destination unit |
| `transferred_by` | FK | Admin who initiated the transfer |
| `reason` | string | e.g. `maintenance`, `upgrade`, `downgrade` |
| `effective_date` | timestamp | When the transfer took effect |

The `Lease` model has a `unitHistories()` HasMany relationship.

**Design rationale:** A lease is a legal agreement between tenant and property, not tenant and unit. Moving to another unit for maintenance does not terminate the agreement — rent, deposit, billing, and terms remain unchanged. The unit transfer is tracked as a history entry rather than creating a new lease, preserving lease continuity and providing a complete audit trail.

### Frontend

`ticket-form-sheet.tsx`:
- Unit select stores `active_lease_count` from the server
- "Block unit for maintenance" checkbox visible when unit is selected in create mode
- Dialog `showOccupiedDialog` triggers when block + occupied:
  - "Move tenant" radio with target unit dropdown (available units, same property, not maintenance)
  - "Keep tenant, just mark as maintenance" radio
- On continue: submits form with `block_unit=true` and optional `move_tenant_to_unit_id`

### Database

No new columns. Uses existing `units.status` column. `active_lease_count` is computed via `withCount` subquery.
