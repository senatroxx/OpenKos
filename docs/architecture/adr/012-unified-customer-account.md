# ADR-012: Unified Customer Account Surface

**Status:** Superseded by ADR-013  
**Date:** 2026-09-23  
**Supersedes:** None

## Context

Applicants and Tenants share one authenticated User, but the application exposed
their customer experience through separate application and Tenant Portal surfaces.
That split made a normal lifecycle transition feel like an account migration and
encouraged redirect decisions based on persona instead of capability.

## Decision

The authenticated customer surface is one Account workspace under `/account`.

- One User, Fortify session, password, and verification flow remain authoritative.
- Applications remain User-owned Application records.
- Tenant and Lease records remain Tenant-domain records and are not created by
  registration or merged with Applications.
- Account navigation exposes Applications for authenticated Users and exposes
  Leases, Billing, Maintenance, and Notifications only when Tenant-domain access
  is available.
- Staff/Owner users retain a separate permission-protected Dashboard and may use
  both surfaces without changing roles or permissions.
- Former `/portal/*` and standalone application URLs redirect to the canonical
  `/account/*` URLs while the underlying controllers, policies, and domain
  workflows remain shared.

Post-authentication routing is resolved centrally: an authorized intended URL wins;
otherwise staff-only users go to `/dashboard` and customer users go to `/account`.

## Consequences

The Account UI progressively gains Tenant capabilities after explicit Tenant
creation/conversion. Applicant ownership never grants Tenant or staff access, and
Tenant linkage never grants staff access. Legacy URLs remain usable through redirects
without creating a second rendered customer application.

Revisit this decision if customer and operational identities ever require separate
sessions or if one User may own multiple independent customer workspaces.
