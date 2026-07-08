# ADR-001: Single Tenant

**Status:** Accepted
**Date:** 2026-07-08

## Context

OpenKOS replaces Excel + WhatsApp workflows for a kos (boarding house) owner. Each owner runs their own business with their own data, staff, and configuration. The alternatives were:

- **Multi-tenant SaaS schema** — every table carries a `tenant_id`, every query is scoped, auth and billing become per-organization.
- **Single tenant per installation** — one installation serves one kos business; isolation comes from running separate installations.

Multi-tenancy taxes every feature (scoping bugs are data-leak bugs), and the target user is a self-hosting owner, not a platform operator.

## Decision

We will build OpenKOS as a **single-tenant application**: one installation = one kos business = one owner.

Concretely:

- Exactly one `owner` account exists, created by `php artisan app:install` (subsequent runs fail).
- Application-wide configuration lives in a single `settings` store, not per-organization rows.
- No table carries a tenant/organization discriminator; a "tenant" in the schema (`tenants` table) means a *renter*, never an organization.
- Serving multiple businesses means multiple installations, not shared infrastructure.

## Consequences

- Every query, policy, and migration is simpler — no scoping layer, no cross-tenant leak class of bugs.
- The `Gate::before` owner bypass and the singleton settings model are valid designs *because* of this decision.
- A hosted offering must provision per-customer installations rather than sharing a database (see [ADR-003](003-cloud-separation.md)).
- **Revisit trigger:** a genuine need to serve many businesses from shared infrastructure with shared operations. Retrofitting `tenant_id` would be a major rework; that cost is accepted knowingly.
