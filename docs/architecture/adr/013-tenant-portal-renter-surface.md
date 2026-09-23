# ADR-013: Tenant Portal as the Renter Surface

**Status:** Accepted  
**Date:** 2026-09-23  
**Supersedes:** ADR-012

## Context

Applicant and Tenant capabilities share one authenticated User, but the previous
decision introduced a separate `/account` customer surface. The product instead
uses the existing Tenant Portal as the single renter-facing experience across the
prospect, applicant, and tenant lifecycle.

## Decision

The authenticated renter surface is `/portal`.

- Portal access requires an authenticated, verified User but does not require a
  Tenant record.
- Applications remain User-owned records and are presented at `/portal/applications`.
- Tenant-domain routes remain separately authorized by Tenant, Lease, billing, and
  maintenance policies.
- Registration creates only a User; explicit application conversion remains the
  only path to Tenant creation.
- Staff/Owner users retain the separate permission-protected `/dashboard` and may
  use both surfaces without changing roles or permissions.
- Former standalone application URLs redirect to `/portal/applications`.

## Consequences

The Portal progressively exposes renter capabilities: overview, applications, and
profile are available to authenticated prospective renters; leases, billing,
maintenance, and notifications appear only when their existing domain
authorization permits them. Portal access is not evidence of Tenant identity.

There is one authentication identity and session. A User can simultaneously be an
applicant, Tenant, and staff member, with each capability independently authorized.
