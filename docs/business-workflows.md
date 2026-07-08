# Business Workflow Architecture

> **Status:** Living document
> **Purpose:** Establish conventions for Actions, Data objects, Results, Business classes, and Repositories.

## Data Flow

```
HTTP Request
  → Controller (validates, authorizes)
    → Action (orchestrates)
      → Business (domain logic, returns DTOs)
      → Repository (data access, returns models)
      → Notification (dispatches)
```

## When to Create Each Layer

### Action (`app/Actions/{Domain}/`)

Create an Action when an operation involves:

- Multiple steps that should be testable in isolation
- A database transaction
- Orchestration between Business rules, Repositories, and Notifications

Actions are **injectable classes** (not static). Constructor-inject dependencies. One `execute()` method.

```php
class RenewLease
{
    public function __construct(
        private RenewalEligibilityChecker $eligibility,
        private LeaseFinancialChecker $financial,
    ) {}

    public function execute(Lease $lease, RenewLeaseData $data): RenewLeaseResult
    {
        // orchestrate: check eligibility → check finances → transaction → return result
    }
}
```

### Business (`app/Business/{Domain}/`)

Create a Business class when:

- A pure domain rule is used in more than one place
- A calculation deserves its own unit tests
- The logic has no side effects (no DB, no notifications, no framework dependencies beyond Carbon)

Business classes are **stateless**. Methods take primitives or models, return primitives or DTOs.

```php
class RenewalEligibilityChecker
{
    public function canRenew(Lease $lease): bool
    {
        return $lease->status === 'active' && $lease->end_date !== null;
    }
}
```

### Data (`app/Data/{Domain}/`)

Create a Data object when:

- An Action needs structured input typed as explicit parameters rather than a raw array
- A DTO is shared between an Action and a Form Request's `toData()` method

Use `final readonly class` with typed public properties.

### Result (`app/Results/{Domain}/`)

Create a Result object when:

- An operation can fail in expected ways
- The caller needs to check success/failure and access result data

Include `succeeded()`, `failed()`, `static success()`, and `static error()` helpers.

### Repository (`app/Repositories/{Domain}Repository.php`)

Create a Repository when:

- Persistence logic involves dedup, unique constraints, or complex filtering
- A query needs to be mockable for tests

One class per aggregate. Thin wrappers over Eloquent.

## Controller Conventions

Controllers should:

1. Validate via Form Request (injected as parameter)
2. Authorize via Policy (`$this->authorize()`)
3. Construct a Data object from the request
4. Call the Action's `execute()` method
5. Check the Result for success/failure
6. Flash a toast and redirect

```php
public function renew(RenewLeaseRequest $request, Lease $lease, RenewLease $action): RedirectResponse
{
    $this->authorize('renew', $lease);

    $result = $action->execute($lease, $request->toData());

    if ($result->failed()) {
        Inertia::flash('toast', ['type' => 'error', 'message' => $result->error]);
        return back();
    }

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Lease renewed.')]);
    return to_route('leases.index');
}
```

## Directory Layout

```
app/
├── Actions/
│   ├── Leases/
│   │   ├── CreateLease.php
│   │   ├── MoveOutLease.php
│   │   ├── RecordPayment.php
│   │   └── RenewLease.php
│   ├── Payments/
│   │   └── RecordPayment.php
│   └── Reminders/
│       ├── ForceSendReminder.php
│       └── SendRentReminders.php
├── Business/
│   ├── Leases/
│   │   ├── OccupancyCalculator.php
│   │   ├── LeaseFinancialChecker.php
│   │   └── RenewalEligibilityChecker.php
│   └── Reminders/
│       └── PaymentReminderScheduler.php
├── Data/
│   ├── Lease/
│   │   ├── CreateLeaseData.php
│   │   ├── MoveOutLeaseData.php
│   │   └── RenewLeaseData.php
│   ├── Payment/
│   │   └── RecordPaymentData.php
│   └── Reminder/
│       ├── ReminderEvent.php
│       └── ReminderSettings.php
├── Results/
│   └── Lease/
│       ├── MoveOutLeaseResult.php
│       └── RenewLeaseResult.php
├── Payment/
│   └── RecordPaymentResult.php
└── Repositories/
    └── ReminderRepository.php
```

## Current Workflows (refactored)

| Workflow            | Controller                                              | Action              | Data                | Result                | Business                                             |
| ------------------- | ------------------------------------------------------- | ------------------- | ------------------- | --------------------- | ---------------------------------------------------- |
| Create Lease        | `LeaseController::store()`                              | `CreateLease`       | `CreateLeaseData`   | —                     | `OccupancyCalculator`                                |
| Renew Lease         | `LeaseController::renew()`                              | `RenewLease`        | `RenewLeaseData`    | `RenewLeaseResult`    | `RenewalEligibilityChecker`, `LeaseFinancialChecker` |
| Move Out / Transfer | `LeaseController::moveOut()`, `LeaseController::move()` | `MoveOutLease`      | `MoveOutLeaseData`  | `MoveOutLeaseResult`  | `OccupancyCalculator`                                |
| Record Payment      | `PaymentController::store()`                            | `RecordPayment`     | `RecordPaymentData` | `RecordPaymentResult` | —                                                    |
| Force Reminder      | `LeaseController::sendReminder()`                       | `ForceSendReminder` | —                   | —                     | —                                                    |
| Scheduled Reminders | Console Command                                         | `SendRentReminders` | —                   | —                     | `PaymentReminderScheduler`                           |

## Non-Examples (keep thin)

These controller methods are already compliant and should NOT be extracted:

- `LeaseController::update()` — 3 lines of logic
- `LeaseController::destroy()` — simple status update
- `PaymentController::verify()` — simple status update
- `PaymentController::proof()` — single file response
