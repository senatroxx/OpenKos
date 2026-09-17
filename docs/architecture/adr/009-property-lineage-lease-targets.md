# ADR-009: Property-Lineage Lease Targets

**Status:** Accepted
**Date:** 2026-09-17

## Context

ADR-004 established `Unit` as the universal rentable thing and attached every
Lease to a Unit. That is sufficient for unit inventory, but not for properties
that are rented as a single whole. Representing a whole-property lease with a
fake or synthetic Unit would corrupt Unit operational status, capacity,
utility-meter, inspection, maintenance, and historical reporting semantics.

OPE-220 also has to support hybrid properties, where a property can expose both
whole-property and Unit targets without allowing the two forms of occupancy to
overlap.

## Decision

We supersede the lease-target portion of ADR-004. The remaining ADR-004
decisions remain valid: `PropertyType` is the property-type seam and `Unit` is
the universal physical inventory record for Unit-rental behavior.

Every Lease belongs directly to a Property through required `property_id`.
`unit_id` is optional:

- A Unit Lease has `property_id` and `unit_id`, no `property_rate_id`, and may
  retain the existing `unit_rate_id` behavior.
- A whole-property Lease has `property_id`, a null `unit_id`, a
  `property_rate_id`, and a null `unit_rate_id`.

`property_id` is authoritative lineage; it is not derived through Unit. New
Unit Leases must match the Unit's Property. Database constraints and model
guards enforce target and rate ownership. The presentation layer exposes
`target_type` so consumers do not repeatedly infer a domain target from a
nullable relation.

Active occupancy uses the shared Lease active semantics. On a hybrid Property,
an active whole-property Lease blocks every new or renewed Unit Lease, and any
active Unit Lease blocks a new or renewed whole-property Lease. Unit-versus-Unit
conflicts continue to follow the existing Unit capacity and co-tenancy rules;
an occupied Unit does not block unrelated Units. Creation and renewal serialize
on the Property row before locking required Units and Lease/rate records.

## Consequences

- Lease, Invoice, payment, reminder, and renewal history remains attached to a
  Lease. `rent_amount`, `currency`, `billing_interval`, and `billing_unit` are
  the Lease's authoritative historical billing snapshots;
  `property_rate_id` records pricing lineage and is not live financial truth.
- Unit move-out continues to update Unit operational status. Whole-property
  move-out has no Unit to mutate. Renewal rechecks the same Property-level
  conflict rule for both target types.
- Unit-specific inspections and maintenance remain Unit-scoped when a Unit is
  known. Whole-property maintenance and inspection context is Property-scoped;
  no synthetic Unit or unrelated “common area” classification is introduced.
- Occupancy and public availability distinguish Unit operational status from
  rental availability. An active whole-property Lease makes all Units
  unavailable for new rental while their operational statuses remain intact.
  Hybrid whole-property availability requires no active Property inventory
  Lease; hybrid Unit availability additionally checks the whole-property
  conflict.
- Property archival remains Property-scoped and refuses to archive while any
  active Lease, including a whole-property Lease, is present. Staff access,
  tenant views, and exports use the Lease's direct Property lineage.
- The migration adds and backfills `leases.property_id` from existing Unit
  lineage, makes `unit_id` nullable for whole-property targets, and preserves
  existing Unit Leases. A rollback that cannot represent newly-created
  whole-property Leases fails safely instead of fabricating Units or discarding
  data.
- Future booking, reservation, or other target types may build on explicit
  Property lineage and target semantics, but are outside this decision. The
  concrete revisit trigger is a new target whose lifecycle cannot be expressed
  by either a whole Property or an existing Unit without introducing date-range
  or inventory semantics.
