# ADR-007: Invoice Aggregate for Lease Billing

**Status:** Accepted
**Date:** 2026-07-08

## Context

The original billing model was `Lease → Payment → PaymentProof`, with payments attached to leases through a `paymentable` polymorph and carrying their own `period_start`/`period_end`. That conflated the obligation (rent due) with the settlement (money received): there was no row representing unpaid rent, reminders and outstanding balances were recomputed from `Lease::schedule()` on every read, partial payments were unrepresentable, and a future payment gateway would have had nothing concrete to pay against.

Alternatives considered: keeping payments-as-obligations and layering more logic on the computed schedule (rejected — every consumer reimplements "is this period paid"), and lazily materializing invoices only when a payment arrives (rejected — unpaid rent still has no first-class row, which is the main problem).

## Decision

We introduce an `invoices` aggregate between Lease and Payment: `Lease → Invoice → Payment → PaymentProof`.

- An invoice is the billing obligation: period, due date, `total`, `amount_paid`, status. Snapshot detail lives in `invoice_line_items` (currently a single `rent` line), so historical invoices stay accurate when lease pricing changes.
- A payment settles exactly one invoice (`payments.invoice_id`, NOT NULL). The `paymentable` polymorph and payment period columns are gone. Payment proofs stay attached to payments.
- Invoices are **materialized upfront by the scheduler**: `invoices:generate` runs daily at 01:00 and creates Pending invoices for each active lease up to a 2-month horizon, backed by a unique `(lease_id, period_start)` index for idempotency. Lease creation, renewal, and unit transfer generate synchronously so the UI never waits for the scheduler. Move-out cancels future untouched Pending invoices.
- **Invariant:** the generation horizon (2 months) must stay ≥ the reminder lookahead, and the generator is scheduled before the 08:00 reminder run — reminders read only from invoices and must never encounter an un-invoiced period.
- Due dates support **advance** (default: due within the billed period, on `rent_due_day`) and **arrears** (due one billing period after consumption) via `leases.billing_strategy`. The strategy only shifts due-date derivation in `Lease::schedule()`; the generation engine is strategy-agnostic.
- Invoice status is recomputed from confirmed payments (`Invoice::recalculateStatus()`): Paid when `amount_paid >= total`, Partial when anything is confirmed, else Pending. Cancelled/Void are terminal manual states. **Overdue is derived**, not stored: `payable + due_date < today`. A stored Overdue would need a daily status-flip job and drifts when due dates change; a where-clause is free.
- Invoice and Payment both use the `Auditable` trait — money mutations land in the immutable `audit_logs` trail. The existing `PaymentRecorded`/`PaymentStatusChanged` events are unchanged (invoice context reaches listeners via the `invoice` relation). `InvoiceGenerated` fires after each invoice the engine creates (outside the transaction) — the subscription point for payment gateways and the tenant portal.
- Lease deposits stay as columns on `leases` for MVP; deposit-as-invoice-type is a future milestone.
- Pre-alpha, no data migration: the payments table was dropped and recreated.

## Consequences

Easier: unpaid/partial/overdue rent are queryable rows; reminders and dashboards read invoice state instead of recomputing schedules; payment gateways create payments against an invoice id; billing history survives lease price changes.

Harder: invoice rows must exist before payment (the generator and its invariant are load-bearing); a second aggregate to keep consistent (status recompute must run after every payment mutation).

Given up: multi-invoice payments (one transfer covering several months needs splitting into one payment per invoice), and `Lease::schedule()` remains only as the period calculator feeding generation.

Revisit when: deposits or non-rent charges become invoice types, a gateway needs webhook-driven settlement, or one-payment-many-invoices becomes a real workflow (introduce a payment allocation table then).
