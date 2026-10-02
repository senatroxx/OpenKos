# ADR-014: Application, Reservation, and Lease Lifecycle

**Status:** Accepted
**Date:** 2026-09-28
**Amends:** ADR-013's standalone Application-to-Tenant conversion timing only

## Context

OPE-197 established Applications as User-owned rental intent and the Tenant
Portal as the renter surface. Its independent Application-to-Tenant conversion
step creates a Tenant before there is a confirmed inventory commitment or Lease.
Reservations need to connect an accepted Application to the existing Lease
workflow without changing the Portal architecture or ordinary Lease conflict
semantics.

## Decision

An accepted Application may request one Reservation at a time from the Tenant
Portal. The request starts Pending and claims no inventory. An operator may
confirm it after a transactional availability check. A UnitType Application is
assigned one available physical Unit at confirmation and claims one occupant
slot; a whole-property Application claims the Property. The hold starts at
confirmation and expires after a configurable duration, defaulting to 48 hours.
The claim protects occupancy from the requested move-in date onward.

Only the operator explicitly creates a Lease from a Confirmed Reservation. The
existing Lease workflow receives the selected target and applicant, applies its
pricing and business rules, creates or reuses the applicant's Tenant record, and
marks the Reservation Converted on success. A failed Lease creation leaves the
Reservation Confirmed and does not prematurely create a Tenant. Cancelled,
rejected, or expired Reservations allow another Reservation request from the
same accepted Application. Converted Reservations do not. Pending and Confirmed
Reservations may be cancelled; operators may reject Pending requests. Expiry
releases a Confirmed hold. Reservation deposit and gateway payment are outside
this lifecycle.

Reservation claims and Reservation-versus-Lease checks use the Reservation's
move-in date and a Lease's end date, with a missing Lease end date treated as
open-ended. Unit capacity includes existing overlapping active Lease occupants
and confirmed Reservation slots. Unit confirmation also requires any
status-active Lease on the selected Unit to have the same start date as the
Reservation. This preserves the existing Lease workflow's grouping of Unit
occupants into one Lease. A whole-property Reservation cannot be confirmed
while any Lease on the Property is status-active, even if its end date is before
the Reservation move-in date, because the existing whole-property Lease
workflow rejects creation until those Leases are closed. Whole-property claims
conflict with Unit claims and Leases across the Property. Ordinary
Lease-versus-Lease conflict behavior remains status-based under ADR-009 and is
not redefined here.

## Consequences

- ADR-013 remains accepted for the /portal renter surface, User-owned
  Applications, and independent staff/Tenant capabilities. Only its
  standalone Application-to-Tenant conversion step is amended.
- Accepted applicants remain Users without Tenant records until successful
  Lease creation from a Confirmed Reservation.
- Inventory is not guaranteed while a Reservation is Pending. Confirmed holds
  are temporary; applicants must complete explicit Lease creation before the
  expiry timestamp.
- Revisit hold duration and reservation payment when measured conversion
  behavior or a defined refundable-payment/accounting model requires it.
