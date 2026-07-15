# ADR-004: Rental Unit Abstraction

**Status:** Accepted
**Date:** 2026-07-08

## Context

OpenKOS started as kos (boarding house) management, but properties come in flavors — kos, apartment, villa, hostel, hotel. The alternatives were:

- **Kos-specific model** — `rooms` belonging to `boarding_houses`; simplest, but every new property type forces a schema rework.
- **Per-type models** — separate entities per property type; heavy duplication for near-identical behavior.
- **Generic rental unit** — one `Unit` entity under a typed `Property`, with type-specific behavior branching on an enum when it actually diverges.

## Decision

We will model everything rentable as a **Unit belonging to a typed Property**.

- `Property` carries a `type` backed by the `App\Models\PropertyType` Eloquent model: `boarding_house` (default), `apartment`, `villa`, `hostel`, `hotel`. The model is the *seam* for future divergence — no behavior branches on it yet, deliberately. Property types are user-managed via `Settings\PropertyTypeController`.
- `Unit` is the universal rentable thing: it owns pricing history (`UnitRates`), lifecycle status (`App\Enums\UnitStatus`), and `capacity`.
- Leases attach to Units, tenants attach to Leases (`lease_tenant` pivot, multi-occupant with a `primary_tenant_id`), payments attach to Leases. None of this chain knows the property type.
- Domain vocabulary: a **tenant** is a person renting; a **unit** is what they rent; a **property** groups units.

## Consequences

- Supporting a new property type is a new row in the `property_types` table plus whatever behavior actually differs — no schema change.
- The lease/payment/reminder machinery is written once and works for all property types.
- Kos-specific conveniences can't be hardcoded; they must branch on `PropertyType` when introduced, keeping the divergence visible in one place.
- **Revisit trigger:** a property type whose rentable thing doesn't fit the Unit shape (e.g. time-slot bookings for hotel-style nightly rental). That likely means a booking abstraction *alongside* leases, recorded in a new ADR.
