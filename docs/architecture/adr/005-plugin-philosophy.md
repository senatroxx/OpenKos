# ADR-005: Plugin Philosophy

**Status:** Accepted
**Date:** 2026-07-08

## Context

OpenKOS must be extensible (navigation, dashboards, workspace tabs, settings pages, notification drivers, payment gateways) without forks. The alternatives were:

- **Sandboxed plugins** — isolated processes or capability-limited runtimes; safe for untrusted marketplace code, enormously complex.
- **Hook/filter system** (WordPress-style) — stringly-typed, hard to type-check, invites core monkey-patching.
- **In-process trusted plugins** — first-class PHP classes registering into typed registries, trusted like any Composer dependency.

## Decision

We will treat plugins as **trusted, in-process extensions registering into typed platform registries** — the trust boundary is installation, not a runtime sandbox.

- A plugin extends `OpenKOS\Platform\Plugin\Plugin`, declares a `PluginManifest` (id, version, `coreVersion` constraint, dependencies), and registers into container-singleton registries via the `OpenKOSManager`. Enabled plugins are listed explicitly in `config/platform.php`.
- Extension is **additive only**: plugins own their tables via their own migrations, declare their own permissions, and must not alter core schema or behavior.
- Plugins react to core through **domain events** (`App\Events`, see [docs/domain-events.md](../../domain-events.md)) — the stable plugin API — never by patching core code.
- The loader fail-fasts on incompatible core versions, missing dependencies, and cycles; a broken plugin never half-boots.
- Dormant seams are interface-only until a real consumer forces their shape (e.g. `PaymentGateway` waits for the first real gateway; `PluginDiscovery` waits for Composer-based distribution).

Full mechanics: [docs/platform.md](../../platform.md).

## Consequences

- Plugins get real types, IDE navigation, DI, and the whole framework — no capability API to design or maintain.
- Users must vet plugins like Composer dependencies; there is no protection from a malicious plugin by design.
- An untrusted-plugin marketplace would require sandboxing this architecture deliberately does not provide — that is a superseding ADR, not an increment.
- **Revisit trigger:** demand for third-party plugins distributed outside the repo (the frontend bundling problem) or from untrusted authors (the sandbox problem).
