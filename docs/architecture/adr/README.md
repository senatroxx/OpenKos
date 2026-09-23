# Architecture Decision Records

Architectural decisions with real trade-offs are recorded here as ADRs, so future contributors can find the *why* without re-excavating old discussions and code reviews.

## Index

| ADR                                       | Title                   | Status   |
| ----------------------------------------- | ----------------------- | -------- |
| [001](001-single-tenant.md)               | Single Tenant           | Accepted |
| [002](002-modular-monolith.md)            | Modular Monolith        | Accepted |
| [003](003-cloud-separation.md)            | Cloud Separation        | Accepted |
| [004](004-rental-unit-abstraction.md)     | Rental Unit Abstraction | Superseded by ADR-009 |
| [005](005-plugin-philosophy.md)           | Plugin Philosophy       | Accepted |
| [006](006-api-strategy.md)                | API Strategy            | Accepted |
| [007](007-invoice-aggregate.md)           | Invoice Aggregate       | Accepted |
| [008](008-tenant-identity.md)             | Tenant Identity         | Superseded by ADR-011 |
| [009](009-property-lineage-lease-targets.md) | Property-Lineage Lease Targets | Accepted |
| [010](010-unit-type-pricing-hierarchy.md) | Unit Type Pricing Hierarchy | Accepted |
| [011](011-dual-persona-identity.md)      | Dual-Persona User Identity | Accepted |
| [012](012-unified-customer-account.md)   | Unified Customer Account Surface | Superseded by ADR-013 |
| [013](013-tenant-portal-renter-surface.md) | Tenant Portal as Renter Surface | Accepted |

Future ADR candidates: the Activity Log vs Audit Log split (`activity_logs` written by `RecordActivitySubscriber` from domain events; `audit_logs` written directly by actions like `UpdateSettings`) is a decision worth recording once it stabilizes.

## Process

1. Copy [template.md](template.md) to `NNN-short-kebab-title.md` — `NNN` is the next number in the index, zero-padded to three digits.
2. Fill it in. Keep it short; an ADR records a decision, it is not a design doc.
3. Add the ADR to the index table above.
4. Open the ADR in the same PR as the change it justifies.

**When to write one:** the change picks between real alternatives with lasting consequences (a boundary, a storage model, a dependency, a protocol). Routine implementation choices already covered by [docs/architecture.md](../../architecture.md) don't need one.

**Changing a decision:** don't edit an accepted ADR's decision. Write a new ADR that supersedes it, set the old one's status to `Superseded by ADR-NNN`, and update the index.

Statuses: `Proposed` → `Accepted` → (`Deprecated` | `Superseded by ADR-NNN`).
