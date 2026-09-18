# ADR-010: Unit Type Pricing Hierarchy

**Status:** Accepted
**Date:** 2026-09-17

## Context

Units of the same Unit Type need shared default rates while retaining explicit
unit-level overrides. A unit override must not hide other billing variants or
currencies inherited from its type.

## Decision

Unit Type rates are defaults. Unit rates are explicit overrides. Effective
pricing is resolved centrally by `EffectiveUnitRateResolver` using the exact
identity `(billing_interval, billing_unit, currency)`:

- an active UnitRate replaces the active UnitTypeRate only for the same identity;
- unrelated UnitTypeRates remain effective;
- an inactive UnitRate does not override, so the active UnitTypeRate is exposed;
- leases retain the selected source ID (`unit_rate_id` or `unit_type_rate_id`)
  while amount, currency, interval, and unit remain historical snapshots.

All lease, assignment, maintenance transfer, move, listing readiness, public
starting-price, and operational pricing presentation flows consume this
resolver. PropertyRate remains the whole-property pricing source.

## Consequences

The resolver is the only merge implementation and is covered by precedence and
fallback tests. Existing UnitRates remain explicit overrides; no data migration
silently moves or deduplicates them. Unit Type rate import/export is outside
this decision.
