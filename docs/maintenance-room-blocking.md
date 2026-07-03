# Maintenance Room Blocking

> **Status:** Implemented
> **Issue:** [OPE-35](https://linear.app/openkos/issue/OPE-35)

## Overview

When a maintenance ticket is created for a room, the admin can optionally block the room for maintenance. A blocked room cannot receive new leases, assignments, or tenant moves. The room is restored when the ticket is resolved and the admin opts to restore availability.

## Room Status Model

The `rooms.status` column holds one of four values:

| Status | Meaning |
|--------|---------|
| `available` | Empty room, ready for lease |
| `occupied` | Room has an active lease |
| `maintenance` | Room is blocked for maintenance |
| `unavailable` | Room is blocked for other reasons (manual) |

## Blocking Flow

1. Admin creates a maintenance ticket with a room selected
2. Checks "Block room for maintenance" checkbox
3. If room is **empty** → room status set to `maintenance` immediately
4. If room is **occupied** → a dialog appears with options:
   - **Move tenant to another room**: selects a target room, creates the ticket, terminates the old lease, creates a new lease on the target room, and blocks the original room. The target room is set to `occupied`.
   - **Keep tenant, just mark as maintenance**: creates the ticket, sets room to `maintenance`. The tenant stays with their active lease but the room won't appear in available-room dropdowns.

## Enforcement

### Lease Creation
`LeaseController::store()` aborts with 422 if room status is `maintenance`.

### Tenant Assignment
`TenantController::assignRoom()` aborts with 422 if room status is `maintenance`.

### Lease Move
`LeaseController::move()` aborts with 422 if target room status is `maintenance`.

`LeaseController::moveOut()` aborts with 422 if target room status is `maintenance` (when `move_to_another_room` is selected).

### Available Room Queries
All four "available rooms" queries now exclude maintenance rooms:

- `LeaseController::index()` — room-specific lease page
- `LeaseController::globalIndex()` — global leases page
- `TenantController::index()` — tenant list
- `RoomController::index()` — room list

Filter: `->whereNotIn('status', ['maintenance'])`

### Status Protection
When a lease is terminated (`destroy()`, `move()`, `moveOut()`), the room is only set back to `Available` if its current status is not `maintenance`. This prevents accidentally overriding a blocked room.

## Restoring a Room

When editing a ticket (status change via the Resolve or Cancel action), if the ticket's room is in maintenance, a "Restore room availability" checkbox appears.

- **Checked + no other open tickets for this room**: room status set to `Available` (if no active lease) or `Occupied` (if active lease exists)
- **Checked + other open tickets exist**: room stays in `maintenance` (flash message explains why)
- **Unchecked**: room stays in `maintenance`

## Technical Details

### Request Fields

**StoreMaintenanceTicketRequest:**
- `block_room` — boolean, optional. When true, sets the selected room's status to `maintenance`.
- `move_tenant_to_room_id` — integer, optional. When provided with `block_room`, moves the active lease from the blocked room to the target room before blocking.

**UpdateMaintenanceTicketRequest:**
- `restore_room` — boolean, optional. When true on status transition to Resolved/Cancelled, restores the room's availability if no other open tickets exist.

### Controller Logic

`MaintenanceTicketController::blockRoom()` handles the blocking logic:
1. Locks the room with `lockForUpdate()`
2. Finds active lease if any
3. If active lease + move target:
   - Locks target room with `lockForUpdate()`
   - Validates target is not under maintenance, not same room, no active lease
   - Validates capacity
   - **Transfers the lease**: updates `lease.room_id` to target room, appends transfer note
   - Records `LeaseRoomHistory` entry (from_room, to_room, reason=maintenance, transferred_by)
   - Sets original room to `maintenance`, target to `occupied`
   - Lease agreement remains intact — only the physical room changes
4. If active lease + no move: sets room to `maintenance` with warning
5. If no active lease: sets room to `maintenance`

### Room Transfer History

A `lease_room_histories` table tracks every room change within a lease:

| Column | Type | Description |
|--------|------|-------------|
| `lease_id` | FK | The lease being transferred |
| `from_room_id` | FK | Original room |
| `to_room_id` | FK | Destination room |
| `transferred_by` | FK | Admin who initiated the transfer |
| `reason` | string | e.g. `maintenance`, `upgrade`, `downgrade` |
| `effective_date` | timestamp | When the transfer took effect |

The `Lease` model has a `roomHistories()` HasMany relationship.

**Design rationale:** A lease is a legal agreement between tenant and property, not tenant and room. Moving to another room for maintenance does not terminate the agreement — rent, deposit, billing, and terms remain unchanged. The room transfer is tracked as a history entry rather than creating a new lease, preserving lease continuity and providing a complete audit trail.

### Frontend

`ticket-form-sheet.tsx`:
- Room select stores `active_lease_count` from the server
- "Block room for maintenance" checkbox visible when room is selected in create mode
- Dialog `showOccupiedDialog` triggers when block + occupied:
  - "Move tenant" radio with target room dropdown (available rooms, same property, not maintenance)
  - "Keep tenant, just mark as maintenance" radio
- On continue: submits form with `block_room=true` and optional `move_tenant_to_room_id`

### Database

No new columns. Uses existing `rooms.status` column. `active_lease_count` is computed via `withCount` subquery.
