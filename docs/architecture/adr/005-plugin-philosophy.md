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

- A plugin extends `OpenKOS\Platform\Plugin\Plugin`, declares a `PluginManifest` (id, version, dependencies), and declares `openkos/platform` and PHP requirements in Composer. Plugins register into container-singleton registries via the `OpenKOSManager`. In-repo plugins are listed explicitly in `config/platform.php`; trusted Composer plugins may be discovered by the host.
- Extension is **additive only**: plugins own their tables via their own migrations, declare their own permissions, and must not alter core schema or behavior.
- Plugins react to core through **platform-level domain events** (`OpenKOS\Core\Events`, see [docs/domain-events.md](../../domain-events.md)) — the stable plugin API — never by patching core code. Application events remain a compatibility surface for host-only subscribers.
- Composer validation owns platform compatibility; the loader fail-fasts on missing dependencies and cycles, so a broken plugin never half-boots.
- Dormant seams are interface-only until a real consumer forces their shape (e.g. `PaymentGateway` waits for the first real gateway).

Full mechanics: [docs/platform.md](../../platform.md).

## Consequences

- Plugins get real types, IDE navigation, DI, and the whole framework — no capability API to design or maintain.
- Users must vet plugins like Composer dependencies; there is no protection from a malicious plugin by design.
- An untrusted-plugin marketplace would require sandboxing this architecture deliberately does not provide — that is a superseding ADR, not an increment.
- **Revisit trigger:** demand for third-party plugin frontend bundling or support for untrusted authors (the sandbox problem).
