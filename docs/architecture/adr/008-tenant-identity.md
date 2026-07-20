# ADR-008: Tenant Identity

**Status:** Accepted
**Date:** 2026-07-20

## Context

Tenants are first-class domain records (`tenants` table) — the people who rent units. Previously they also carried an optional `email` column used purely as a contact field for reminders. The application now lets an owner invite a tenant into the app so the tenant can authenticate, pay rent, file maintenance tickets, view invoices, and see their lease.

This created a modelling question: when a domain entity (Tenant) needs to become an authenticating user, how do the two concepts relate — and where does each piece of data live?

The realistic alternatives:

- **Tenant as a role.** Assign tenants a `tenant` role on the `users` table; treat them as a specialisation of User. Authentication is trivially solved, but the Tenant domain is now coupled to the auth/RBAC model: every change to role semantics ripples into tenant behaviour, and the Users page must either admit tenants or grow filters to exclude them.
- **Duplicate identity columns.** Keep `tenants.email` as the contact field and add a separate `users.email` for auth. Two identities per tenant, no enforced link, no single source of truth for "where do we send mail."

Both approaches were rejected during [OPE-17](https://linear.app/openkos/issue/OPE-17): they leak auth concerns into the Tenant domain or invite drift between two email columns with no way to reconcile them.

## Decision

We will model the Tenant↔User relationship as a **1:1 optional link** via `tenants.user_id` (nullable, unique, FK → `users.id`, `nullOnDelete`). A Tenant is a domain record; a User is an identity. Linking them is what grants the tenant app access.

Concretely:

- `users.email` is the **single source of truth** for authentication and for any notification delivered to that person. There is no `tenants.email`.
- `tenants.user_id` is nullable: a tenant can exist without an account (the default — contact-only via phone, or no app access). Linking creates the account.
- `tenants.user_id` is unique: one user backs at most one tenant (1:1).
- Tenant is **not** a role. Tenants are not in the Spatie role/permission graph. Owner/staff remain the only entries on the Users page; tenant accounts are managed from the Tenant module.
- A linked user is created inactive (`is_active = false`, random 64-char password). The owner invites via the Tenant detail page; activation happens through Fortify's password broker (`Password::broker()->createToken()`) with a `TenantInvitation` notification. The tenant sets their password and verifies email via a dedicated acceptance route.
- Any code path that previously read `tenant->email` (rent reminders, contact resolution) now reads `tenant->user?->email`. An email contact exists only when a linked user exists.

The `tenants.email` column was dropped irreversibly in migration `2026_07_18_000000_drop_email_from_tenants_table`. Existing rows with a contact email were backfilled into a linked (inactive) User; rows whose contact email already existed as a User with a *different* tenant linkage were left unlinked rather than hijacking an account, and a tripwire throws if a linked tenant's stored email disagrees with its user's login email.

## Consequences

- The Tenant domain stays free of auth concerns. `Tenant` is a renters record; `User` is an identity. The only coupling is one nullable FK.
- The Users page stays Owner/Admin/Staff-only by construction — no filters, no special-case exclusion, because tenants are simply not in that table's domain.
- Notifications and contact resolution require a null-check on `tenant->user` (was previously a null-check on `tenant->email`). Code that assumed `email` always exists on a Tenant now must traverse `user`.
- A tenant cannot have a contact email distinct from their login email. This is deliberate: one identity per person. If a future case needs a separate billing-only email, that becomes a new column with a new purpose — not a revival of `tenants.email`.
- Joining tenant data to auth data is a `tenants.user_id → users.id` join. Reporting/auth flows that need both pay one indexed FK lookup.
- **Revisit trigger:** a tenant needs to back more than one user (shared login for a couple on a joint lease, or a tenant acting on behalf of another). The 1:1 constraint would then need to become 1:N with a notion of "primary" user, mirroring the lease_tenant pattern.
