# ADR-011: Dual-Persona User Identity

**Status:** Accepted
**Date:** 2026-09-23
**Supersedes:** ADR-008

## Context

OPE-197 adds applicant accounts that may later become Tenants. A person can
also be an owner or staff member at the same time. Treating the optional
Tenant relationship as an account type would make these capabilities
mutually exclusive and would remove staff access when an applicant converts.

## Decision

User is the authentication identity. Applicant, Tenant, and Staff/Owner are
independent capabilities and relationships:

- a User is an applicant through Applications owned by that User;
- a User is a Tenant through the Tenant relationship;
- a User is staff or an owner through roles and permissions.

These capabilities can coexist. In particular:

```text
user has Tenant != user is prohibited from being staff
```

Staff authorization and visibility use roles and permissions, not the absence
of a Tenant relationship. Tenant-domain access still requires the Tenant
relationship plus the applicable lease and domain eligibility rules. Applicant
access is scoped to Applications owned by the authenticated User.

Tenant-only users without staff roles do not appear in staff management.
Adding or converting a Tenant never removes staff roles, permissions, or
property access. Applicant ownership never grants staff privileges, and Tenant
linkage alone never grants staff privileges.

The `whereDoesntHave('tenant')` convention from ADR-008 is therefore no longer
valid as the staff-management rule. Staff management must instead select users
with staff authorization while continuing to exclude Tenant-only users.

## Consequences

Staff-plus-Tenant users remain manageable according to their staff
authorization, while Tenant-only users remain outside staff management. Login
routing and page authorization must evaluate each capability independently.
No mutually exclusive global `user_type` or persona enum is introduced.

Revisit this decision if one identity must represent multiple Tenant records,
or if applicant, Tenant, and staff capabilities acquire independent lifecycle
or delegation requirements.
