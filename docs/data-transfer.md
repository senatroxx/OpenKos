# Data Transfer

The data transfer screens use versioned CSV files. Imports are validated in full
before any records are created, and commits are atomic.

## Expenses v1

Expense exports use `expenses-v1.csv` with these columns:

| Column | Import | Description |
| --- | --- | --- |
| `property_slug` | Required | Accessible active property slug. |
| `category_slug` | Required | Active expense category slug. |
| `reference` | Required | Non-empty transfer identity within the property. |
| `amount` | Required | Positive decimal using the selected currency's precision. |
| `currency` | Required | ISO 4217 currency code; currencies are never converted. |
| `expense_date` | Required | Calendar date in `Y-m-d` format. |
| `vendor` | Optional | Vendor or payee. |
| `description` | Optional | Expense description. |
| `notes` | Optional | Additional notes. |
| `status` | Optional on import | Exports `active` or `voided`; imports accept only `active` and assign active status internally. |
| `voided_at` | Export only | ISO 8601 void timestamp. |
| `voided_by` | Export only | ID of the user who voided the expense, when available. |
| `void_reason` | Export only | Reason recorded when the expense was voided. |

Imports are create-only. The transfer layer treats `(property, reference)` as
the import identity, trimming references and comparing them case-insensitively.
It rejects duplicates both within the file and against existing expenses,
including voided expenses. References are not a global database uniqueness rule;
manual expense creation retains its existing nullable and non-unique behavior.

Voided expenses and their metadata may be exported for reporting, but voided
rows cannot be imported. Receipt files and media are not included in CSV
transfers.
