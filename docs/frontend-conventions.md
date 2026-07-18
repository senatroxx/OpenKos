# Frontend Conventions

> **Status:** Living document
> **Purpose:** Record the React/Inertia UI conventions so contributors don't re-derive them per component. Backend/layer conventions live in [`docs/architecture.md`](architecture.md); decisions with lasting trade-offs are ADRs in [`docs/architecture/adr/`](architecture/adr/README.md).

Stack: React 19, Inertia 3, TypeScript, shadcn/ui, Tailwind CSS 4, Wayfinder (typed routes). Components live under `resources/js/components/features/**` (feature sheets/dialogs) and `resources/js/components/ui/**` (shadcn primitives).

---

## Forms

### Use `useForm` for data-entry forms

Feature forms (the sheets/dialogs) use Inertia's **`useForm`** hook. Do **not** drive a form with the `<Form>` component fed by reactive-state `<input type="hidden">`, and avoid hand-rolling `useState` for `processing`/`errors` around `router.post`.

**Why:** an `<input type="hidden" value={reactState}>` inside `<Form>` submits a stale value — React updates the hidden input's `value` but fires no `change` event, so the reactive value never reaches the submission. `useForm` keeps submitted data in a typed `data` object and gives `setData`, `processing`, `errors`, `reset`, and `transform` for free. (Inertia v3's `<Form>` itself reads live `FormData` at submit, so a `<Select name>` — which renders a real native `<select>` — still submits correctly; the hidden-input pattern was the failure mode, and `useForm` is the robust default regardless.)

Every submitted field lives in `data` — `useForm` submits the `data` object, not the DOM, so uncontrolled `defaultValue` inputs would not be sent.

```tsx
const { data, setData, submit, reset, processing, errors } = useForm({
    name: record?.name ?? '',
    rent_due_day: '1',
    start_date: todayISO(),
});

function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    submit(isEdit ? update(record!) : store(), {
        onSuccess: () => handleOpenChange(false),
    });
}
```

### Submit via Wayfinder route helpers, passed as objects

Pass the route **definition object** straight to `form.submit(...)` — `store()`, `update(record)`, `tenants.assignUnit({ tenant })`, `properties.units.leases.store({ property, unit })`. Do not extract `.url()`, and do not use the `.post({...})` sub-helper. Dynamic method picks the helper inline: `submit(isEdit ? update(x) : store(), opts)`.

The controller returns `back()` for these mutations (so the caller stays on its current page with fresh data) — `destroy` is the exception. See the redirect convention in [`docs/architecture.md`](architecture.md).

### Preserve exact wire format with `transform`

For fields emitted as `'1'`/`'0'` from a boolean/tri-state, or fields included only conditionally, fold them in at submit with `form.transform()` rather than storing the wire shape in `data`:

```tsx
transform((d) => {
    const payload: Record<string, unknown> = {
        ...d,
        deposit_returned: yes ? '1' : '0',
    };
    if (!hasDeposit) delete payload.deposit_amount;
    return payload;
});
```

### Every control that affects the payload must be state-bound

A radio/checkbox/select that influences what gets submitted must have its selection tracked in state (`checked`/`onChange` bound to a value), and the payload must be derived from that state — never from the DOM, and never inferred from an unrelated field. A "decorative" control (default-checked, `onChange={() => {}}`, or unbound) is a bug waiting to happen: the user's choice is silently ignored.

Concretely, this has bitten us twice — a dialog with "Move tenant / Keep tenant" radios that had no state, so the payload was driven by a leftover select value regardless of which radio was picked. If a control is shown, it must be wired; if it shouldn't affect the payload, don't render it. Gate the submit button (`disabled=…`) when the current selection is incomplete rather than submitting an ambiguous payload.

### Errors and processing

- Render field errors with `<InputError message={errors.x} />` — not an inline `<p className="text-red-500">`.
- Disable the submit button with `disabled={processing}`.

**Exception — file uploads.** Forms that upload a file (the payment sheets) use `router.post(url, formData)` with a native `<form>` and `new FormData(e.currentTarget)`, because file submission needs multipart. They still follow the reset and layout conventions below.

---

## Sheets & dialogs

### One component per action, foldered by the page that uses it

Each action gets its own sheet component, and it lives in the folder of the page that triggers it — not the entity whose record it happens to write.

| Component           | Folder     | Triggered from                |
| ------------------- | ---------- | ----------------------------- |
| `AssignTenantSheet` | `units/`   | Unit page (creates a lease)   |
| `AssignUnitSheet`   | `tenants/` | Tenant page (creates a lease) |

Files named `*-sheet.tsx` render `<Sheet>`. (Lifecycle actions used from several pages — `MoveOutSheet`, `MoveUnitSheet`, `RenewLeaseSheet` — stay in `leases/`.)

### Reset on close

`useForm` seeds its defaults **once on mount**. A sheet that stays mounted and only toggles `open`, or one mounted with `{cond && <Sheet/>}` where `cond` isn't the open state, keeps stale data on reopen. Every form sheet therefore resets on close through one path:

```tsx
function handleOpenChange(next: boolean) {
    onOpenChange(next);
    if (!next) {
        reset();
        // also reset any UI-only useState / refs the form uses
    }
}
```

Route the Radix `onOpenChange`, the Cancel button, and the post-submit `onSuccess` through `handleOpenChange(false)`.

### Remount key goes at the parent

When a sheet must re-seed from a _changing_ record (edit), key the component at the **parent call site**:

```tsx
<UnitFormSheet key={editingUnit?.id ?? 'new'} unit={editingUnit} … />
```

A `key` on the inner `<Sheet>` does **not** reset a `useForm` declared in the component body — `useForm` lives above `<Sheet>`, so re-keying `<Sheet>` only remounts its DOM subtree.

### Detail → action transitions close first

A detail sheet's action buttons (`Edit`, `Assign`, `Move Out`, …) just fire their callback. The **parent handler** closes the detail before opening the next surface, so two sheets never stack:

```tsx
function editFromDetail() {
    setEditingRecord(viewingRecord);
    setDetailOpen(false); // close detail first
    setFormOpen(true);
}
```

(A centered `<Dialog>` opening over a sheet is fine — that's a modal, not a stacked sheet.)

### Action-button layout — `justify-between`, not `SheetFooter`

Do not wrap actions in `SheetFooter` (its `p-4` double-pads). The content container itself pins the buttons to the bottom:

```tsx
<form className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
    <div className="space-y-6">{/* fields */}</div>
    <div className="flex items-center justify-end gap-4">
        <Button
            variant="outline"
            type="button"
            onClick={() => handleOpenChange(false)}
        >
            Cancel
        </Button>
        <Button disabled={processing}>Save</Button>
    </div>
</form>
```

The `<form>` (or Inertia `<Form>`, which forwards `className` to its `<form>`) is the flex-column scroll container; `justify-between` drops the button row to the bottom. Detail sheets use the same shape with a `<div>` instead of `<form>`.

---

## Reuse the shared building blocks

Don't reinvent these — a linter won't catch a hand-rolled duplicate, but a reviewer will.

- **Inputs:** `@/components/ui/input` (`<Input>`), `@/components/ui/textarea` (`<Textarea>`, `min-h-16`). No hand-rolled `<input>`/`<textarea>` carrying the long shadcn className.
- **PhoneInput** (`@/components/shared`): controlled via `value` + `onChange` (emits an E.164 string) under `useForm`. The `name`/`defaultValue` path exists for legacy `<Form>` usage.
- **Formatters** (`@/lib/formatters`): `formatPrice`, `formatRupiah`, `formatDate`, `formatPeriod`, `computeMonthlyEquivalent`, and `todayISO()` (local-date `YYYY-MM-DD` for `type="date"` defaults — do not inline `new Date().toISOString().split('T')[0]`, which is UTC and drifts near midnight).
- **Constants** (`@/lib/constants`): `DUE_DAY_OPTIONS`, `DUE_DAY_LABELS`, `BILLING_UNITS`, `BILLING_STRATEGIES`, `MOVE_OUT_REASONS`, `DEPOSIT_HANDLING_OPTIONS`, `PAYMENT_METHODS`, … Import them; don't redefine locally.
- **Imports:** one statement per module; keep named members ordered.

---

## Verification

Run before opening a PR that touches the frontend:

```bash
npm run lint:check     # eslint (no auto-fix)
npm run types:check    # tsc --noEmit
npm run build          # vite build — also runs the React Compiler
```

There are no frontend unit tests yet (end-to-end testing is deferred — see `docs/architecture.md`).
