# ADR-002: Modular Monolith

**Status:** Accepted
**Date:** 2026-07-08

## Context

OpenKOS needs clear internal structure without operational overhead. The alternatives were:

- **Microservices** — network boundaries between domains; independent deploys at the cost of distributed-systems complexity.
- **Unstructured monolith** — fat controllers and models; fast at first, unmaintainable at scale.
- **Modular monolith** — one deployable, with enforced internal layering and domain slices.

The app is self-hosted by individual kos owners ([ADR-001](001-single-tenant.md)): a single PHP process with a database is the only realistic deployment target. Microservices are off the table on operational grounds alone.

## Decision

We will build OpenKOS as a **modular monolith**: a single Laravel application with strict internal boundaries.

- Application code in `app/` follows the layer architecture (Controllers → Actions → Business/Repositories → Notifications) defined in [docs/architecture.md](../../architecture.md).
- Domains are vertical slices across those layers (`{Layer}/{Domain}/`), e.g. Leases, Reminders.
- Platform/extensibility code lives separately in `src/` under the `OpenKOS\` namespace ([ADR-005](005-plugin-philosophy.md)).
- Domains communicate in-process; cross-domain reactions go through domain events (`App\Events`), not direct coupling.

## Consequences

- One deploy, one database, one test suite — the whole stack is testable with `php artisan test`.
- Refactoring across domains is an IDE operation, not an API-versioning project.
- Discipline is enforced by convention and review rather than by network boundaries; [docs/architecture.md](../../architecture.md) is the contract.
- **Revisit trigger:** none foreseen. Extraction pressure (e.g. a heavy async workload) should first be answered with queues inside the monolith.
