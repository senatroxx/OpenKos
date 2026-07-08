# ADR-006: API Strategy

**Status:** Accepted
**Date:** 2026-07-08

## Context

The frontend needs data and mutations; third parties may eventually want programmatic access. The alternatives were:

- **Public REST/GraphQL API from day one** — the SPA consumes the same versioned API third parties would; every internal change becomes a compatibility question.
- **Inertia-first, no public API yet** — controllers return Inertia pages and redirects; the HTTP layer is an internal implementation detail.

A premature public API is a contract guessed before any consumer exists — the same reasoning that keeps `PaymentGateway` interface-only ([ADR-005](005-plugin-philosophy.md)).

## Decision

We will keep the HTTP layer **Inertia-first with no public API**, until a real external consumer defines one.

- All routes live in `routes/web.php` (plus `settings.php`), session-authenticated, returning Inertia responses or redirects. There is no `routes/api.php`, no token auth, no versioning.
- These endpoints are **internal**: their shapes may change in any release. Nothing outside this repository may depend on them.
- Extension happens through the plugin system ([ADR-005](005-plugin-philosophy.md)) — plugins run in-process and ship their own routes — and through domain events, not through HTTP scraping.
- When a genuine external consumer appears (mobile app, tenant portal, integration), a public API will be introduced deliberately: versioned (`/api/v1`), token-authenticated, delegating to the same Actions the web controllers use — designed against that consumer's needs and recorded in a superseding ADR.

## Consequences

- Controllers stay thin and free to evolve; no API-compatibility tax on internal refactors.
- The layer architecture (Controller → Action → Business/Repository) means a future API is a new thin HTTP skin over existing Actions, not a rewrite.
- Anyone scripting against the web endpoints today is unsupported, by policy.
- **Revisit trigger:** the first committed external consumer. Its concrete needs define `v1` — not speculation.
