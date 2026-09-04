# OpenKOS Load-Test Dataset

This document defines the initial OPE-177 persona and dataset contract for
the standalone OpenKOS k6 project.

## Setup

Load-test data is never part of `DatabaseSeeder`, is rejected in production,
and requires two explicit gates. Run the normal application seed first so
regions and cities exist, then provision the OPE-176 users and dataset:

```bash
LOAD_TEST_FIXTURES_ENABLED=true \
php artisan db:seed --class=Database\\Seeders\\LoadTestSeeder

LOAD_TEST_FIXTURES_ENABLED=true \
LOAD_TEST_DATASET_ENABLED=true \
php artisan db:seed --class=Database\\Seeders\\LoadTestDatasetSeeder
```

The four `LOAD_TEST_*_EMAIL` and `LOAD_TEST_*_PASSWORD` values are required
by `LoadTestSeeder` and must be supplied through an ignored environment file or
secret manager. The dataset seeder only resolves those already-created users;
it does not create or change credentials.

## Dataset contract

The first dataset size is an explicit estimate for initial load-test work, not
a production observation. All rows owned by this fixture use the
`ope-177-load-test` namespace in stable slugs, references, or identifiers.

| Entity | Initial estimate | Purpose |
| --- | ---: | --- |
| Properties | 8 | Multi-property browsing and scope checks |
| Units | 96 (12 per property) | Occupied, available, and write-target pools |
| Tenants | 48 | Search and tenant detail/list workloads |
| Active leases | 48 | One active lease per tenant |
| Historical leases | 12 | Lease history and status coverage |
| Invoices | 144 | Three periods per active lease |
| Payments | 84 | Paid, partial, and pending-review coverage |
| Maintenance tickets | 24 | Reported, in-progress, resolved, and cancelled states |

The first 48 units are occupied by active leases. Remaining units are
available targets for property and unit write flows. The invoice pool includes
paid history, partial balances, payable invoices, upcoming invoices, and
pending payment review records. The first tenant is the configured OPE-176
tenant account and has an active lease, invoice history, and a tenant-created
maintenance ticket.

Rerunning `LoadTestDatasetSeeder` keeps these namespaced counts and
relationships stable. It does not truncate tables or delete unrelated rows.
Scenario-created records outside the namespace are not removed; use a
dedicated non-production database or a clean snapshot when a write-heavy run
must start from the original baseline.

## Personas

The following frequencies and think times are initial assumptions for OPE-178
and OPE-179. They should be refined when runtime observations are available.

| Persona | Representative actions | Relative frequency | Think time | Read/write assumption | Scope |
| --- | --- | --- | --- | --- | --- |
| Owner | Dashboard, properties, units, tenants, leases, invoices, payments, occasional updates | 40% dashboard/property, 40% tenant/lease/billing, 20% writes | 2–5s between reads, 5–10s after writes | 90/10 | All fixture properties |
| Manager (`admin`) | Dashboard, properties, units, tenants, leases, invoice/payment review | 45% property/unit, 35% tenant/lease, 20% billing | 2–5s | 95/5 | All assigned fixture properties |
| Staff | Dashboard, tenant search/detail, lease browsing, operational review | 50% tenants, 30% leases, 20% dashboard | 1–3s | 98/2 | First four fixture properties |
| Tenant | Portal dashboard, lease, billing, invoice history, payment history, maintenance submission | 35% dashboard/lease, 45% billing, 20% maintenance/payment action | 3–8s | 90/10 | Own tenant and lease only |

The percentages are workload-mix assumptions, not assertions about current
production traffic. Closed-model k6 scenarios should implement the stated
think time with `sleep()`; OPE-179 owns the final profile weights.

## Checks and safe writes

Critical checks should confirm that:

- authenticated owner, manager, and staff pages return successful responses;
- tenant dashboard returns an active lease and the expected property/unit;
- billing pages expose both actionable and historical invoice states;
- lease and tenant views stay within the authenticated user's scope;
- write requests target namespaced fixture records and return the expected
  application response.

Use the stable property/unit slugs and lease/invoice/ticket references to
discover records. Do not hard-code database IDs because IDs differ between
environments.

Read pools may be shared across VUs. Write pools must select different
namespaced units, invoices, or maintenance records by VU/iteration so one
record is not the target for every VU. Payment submissions are append-only and
should be limited to the reserved pending-invoice pool or run against a fresh
fixture snapshot.

The current application role mapping is:

```text
owner   -> owner
manager -> admin
staff   -> staff
tenant  -> tenant profile
```

The existing admin and staff permissions are intentionally unchanged by
OPE-177. Manager and staff scenarios should keep unsupported maintenance or
payment writes out of their flows until a separate authorization decision is
made.

## Common k6 tags

Use lower-kebab-case values for the shared vocabulary:

```text
profile   workload profile
persona   owner, manager, staff, or tenant
journey   named user journey
operation measured endpoint or business operation
```

OPE-178 owns the persona scenario implementation. OPE-179 owns the combined
workload profiles, weights, thresholds, and capacity/stress behavior.
