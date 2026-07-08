# ADR-003: Cloud Separation

**Status:** Accepted
**Date:** 2026-07-08

## Context

OpenKOS is open-source and self-hosted first. A hosted ("cloud") offering may exist later. The risk is the common trap where cloud concerns leak into the core: features gated on managed services, code paths that only work on one provider, or open-core crippling where the self-hosted version is a demo.

## Decision

We will keep the **open-source core fully functional and self-hostable, with cloud concerns strictly outside the core**.

- The core must run on commodity infrastructure: PostgreSQL, the `database` queue driver, local/file storage, SMTP. No feature in core may hard-depend on a managed cloud service.
- External integrations go behind pluggable drivers with a self-hosted default (e.g. the WhatsApp channel defaults to `LogDriver`; drivers are registered via the platform `NotificationRegistry`).
- Anything cloud-specific (managed hosting, billing, provisioning, telemetry) is built *around* the core — as separate services or plugins ([ADR-005](005-plugin-philosophy.md)) — never as conditional branches inside it.
- A hosted offering provisions per-customer single-tenant installations ([ADR-001](001-single-tenant.md)); it does not get a privileged multi-tenant fork of the core.

## Consequences

- Self-hosters are first-class: every feature in this repository works without any external account.
- New integrations must ship a driver seam and a default that works offline, which is slightly more work per integration.
- A future cloud product cannot demand core schema or behavior changes for its own convenience; it consumes the same extension points as any plugin.
- **Revisit trigger:** a hosted offering whose requirements genuinely cannot be met through plugins and drivers — that discussion happens in a superseding ADR, not in code.
