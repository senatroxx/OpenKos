---
name: plan-creation
description: 'ACTIVATE when the user asks to create an implementation plan, design a feature, plan work, figure out how to implement something, or before starting any new feature or domain work. This skill ensures every plan considers the full stack — backend, frontend, tests — and follows the project''s layer-first architecture. Do NOT activate for simple bug fixes or one-file changes that don''t need a plan.'
license: MIT
---

# Implementation Plan Creation

Activate this skill whenever you need to create an implementation plan for a feature or domain change. It ensures consistency with the project's architecture, conventions, and tooling.

## 1. Read Foundational Docs

Before writing any plan, read:

- **`docs/architecture.md`** — layer architecture, data flow patterns, domain model, frontend conventions, route structure, database conventions, testing approach
- **`AGENTS.md`** — package versions, skill activation rules, code style, Laravel/Pest/Inertia conventions
- **Existing domain doc** if the feature has one (e.g., `docs/rent-reminders.md`, `docs/multi-tenant-occupancy.md`)

## 2. Activate All Relevant Skills

Load every skill whose domain the feature touches:

- `ponytail` — always load; keeps implementation minimal, avoids over-engineering
- `laravel-best-practices` — DB performance, Eloquent, validation, security, queue/jobs, caching
- `pest-testing` — test structure, assertions, datasets, browser testing
- `tailwindcss-development` — any styling or layout work
- `shadcn` — adding or composing UI components
- `wayfinder-development` — connecting frontend to backend routes/controllers
- `inertia-react-development` — Inertia pages, forms, navigation, deferred props, optimistic updates
- `fortify-development` — any auth work (login, registration, 2FA, passkeys, password reset)

## 3. Use Available MCP Tools

This project has the **Laravel Boost** MCP server configured. Use its tools during planning and implementation:

- `search-docs` — always search before writing code that relies on a package feature; pass `packages` to scope and use multiple queries for OR logic
- `database-schema` — inspect table structure before writing migrations or models
- `database-query` — run read-only queries to understand existing data
- `application-info` — confirm package versions before writing version-dependent code
- `get-absolute-url` — resolve the correct URL for routes before referencing them
- `read-log-entries` / `last-error` / `browser-logs` — when planning a fix, check logs first

## 4. Layer-First Architecture

All new files must be placed in architectural layers, not feature folders:

| Layer | Directory | Responsibility |
|---|---|---|
| Controller | `Http/Controllers/` | HTTP handling, validation, authorization, Inertia response |
| Form Request | `Http/Requests/{Domain}/` | Per-endpoint validation rules |
| Action | `Actions/{Domain}/` | Single-responsibility orchestration |
| Business | `Business/{Domain}/` | Pure domain logic, no side effects |
| Data | `Data/{Domain}/` | Immutable DTOs / value objects |
| Result | `Results/{Domain}/` | Typed success/error results |
| Repository | `Repositories/` | Query abstraction (dedup, complex filtering) |
| Enum | `Enums/` | Type-safe domain constants |
| Policy | `Policies/` | Per-model authorization |
| Notification | `Notifications/` | Notification classes + custom Channels/ + pluggable Drivers/ |
| Service | `Services/` | External integrations |
| Contract | `Contracts/` | Interfaces at system boundaries |
| Model | `Models/` | Eloquent models (avoid bloating — prefer Actions + Business) |

Place files as: `app/{Layer}/{Domain}/{File}.php`

Frontend: `resources/js/{pages|components/features|components/shared|layouts|hooks|types}/{Domain}/`

## 5. Plan Structure Template

A plan must include these sections in order:

### Goal

One-paragraph description of what the feature does and why.

### Data Flow

A short diagram or description of how a request flows through the layers.

### Files to Create / Modify

List every file grouped by layer:

- **Backend:**
  - `app/Enums/{Name}.php` — new cases, values
  - `app/Models/{Name}.php` — new model or new relationships/casts/scopes on existing
  - `app/Http/Controllers/{Name}Controller.php` — new routes or controller methods
  - `app/Http/Requests/{Domain}/{Name}Request.php` — new form requests
  - `app/Actions/{Domain}/{Name}.php` — new action classes
  - `app/Business/{Domain}/{Name}.php` — new business logic
  - `app/Data/{Domain}/{Name}.php` — new DTOs
  - `app/Results/{Domain}/{Name}Result.php` — new result objects
  - `app/Repositories/{Name}Repository.php` — new repositories
  - `app/Policies/{Name}Policy.php` — new policies or new methods
  - `app/Notifications/{Name}.php` — new notification classes
  - `database/migrations/{timestamp}_{name}.php` — new migrations
  - `routes/{web,settings,console}.php` — new routes or schedule entries
  - `config/{name}.php` — new config entries (rare)
- **Frontend:**
  - `resources/js/pages/{domain}/{name}.tsx` — new Inertia pages
  - `resources/js/components/features/{domain}/{name}.tsx` — new feature components
  - `resources/js/components/shared/{name}.tsx` — new shared components
  - `resources/js/layouts/{name}/layout.tsx` — new layout with nav items
  - `resources/js/types/{name}.ts` — new TypeScript types
- **Tests:**
  - `tests/Feature/{Domain}/{Name}Test.php` — feature tests
  - `tests/Unit/{Domain}/{Name}Test.php` — unit tests for pure logic
- **Docs:**
  - `docs/{name}.md` — ADR or design doc (only if explicitly requested)

For each file, note whether it's **new** or **modified**, and describe what changes.

### Key Decisions

List trade-offs made during planning (e.g., "unique constraint for dedup instead of read-then-write", "template resolution at send time not queue time").

### Test Plan

Describe what each test covers. Must include:
- Success path
- Failure / edge cases
- Authorization (if applicable)
- Idempotency (if applicable)

## 6. Plan Review Checklist

Before presenting a plan, verify:

- [ ] Architecture doc and AGENTS.md have been read
- [ ] All relevant domain skills are activated
- [ ] Files are placed in correct layers — no feature-first folders
- [ ] Data flow follows: Controller → Action → Business → Repository (→ Notification)
- [ ] Frontend components follow the pages / features / ui / shared convention
- [ ] TypeScript imports use `@/actions/` or `@/routes/` (Wayfinder), not hardcoded URLs
- [ ] Every new endpoint has an authorization check (Policy or Form Request)
- [ ] Currency values are integers (cents/sen)
- [ ] Migrations use `constrained()` with explicit `cascadeOnDelete` / `nullOnDelete`
- [ ] Test plan covers success path + at least one failure case
- [ ] Domain-specific skills have been loaded for package-specific docs
- [ ] Laravel Boost MCP tools (`search-docs`, `database-schema`, etc.) will be used during implementation

## 7. Final Steps

The plan is done. Present it to the user for approval before implementing.
