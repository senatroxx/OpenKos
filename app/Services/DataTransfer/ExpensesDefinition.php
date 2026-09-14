<?php

namespace App\Services\DataTransfer;

use App\Enums\DataTransferDataset;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\User;
use App\Rules\MoneyAmount;
use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ExpensesDefinition extends DatasetDefinition
{
    public function __construct(private MoneyConverter $moneyConverter) {}

    public function dataset(): DataTransferDataset
    {
        return DataTransferDataset::Expenses;
    }

    public function importHeaders(): array
    {
        return [
            'property_slug',
            'category_slug',
            'reference',
            'amount',
            'currency',
            'expense_date',
            'vendor',
            'description',
            'notes',
            'status',
            'voided_at',
            'voided_by',
            'void_reason',
        ];
    }

    public function exportHeaders(bool $sensitive = false): array
    {
        return $this->importHeaders();
    }

    protected function requiredImportHeaders(): array
    {
        return [
            'property_slug',
            'category_slug',
            'reference',
            'amount',
            'currency',
            'expense_date',
        ];
    }

    public function validateRow(
        array $values,
        int $line,
        User $actor,
        ImportValidationContext $context,
    ): ?array {
        $values = $this->normalizeValues($values);

        if ($values['currency'] !== null) {
            $values['currency'] = strtoupper($values['currency']);
        }

        $valid = $this->validateFields($values, [
            'property_slug' => ['required', 'string', 'max:255'],
            'category_slug' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:255'],
            'amount' => [
                'required',
                'string',
                new MoneyAmount($values['currency'], allowZero: false),
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::in(array_keys($this->moneyConverter->scales())),
            ],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'status' => ['nullable', Rule::in(ExpenseStatus::values())],
            'voided_at' => ['nullable', 'date'],
            'voided_by' => ['nullable', 'integer'],
            'void_reason' => ['nullable', 'string', 'max:65535'],
        ], $line, $context);

        if (! $valid) {
            return null;
        }

        $rowErrorCount = count($context->errors);
        $property = $this->accessibleProperty($actor, (string) $values['property_slug']);

        if ($property === null) {
            $context->error($line, 'property_slug', __('The referenced property could not be resolved or is not accessible.'));
        }

        $category = ExpenseCategory::query()
            ->where('slug', $values['category_slug'])
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            $context->error($line, 'category_slug', __('The referenced expense category could not be resolved or is inactive.'));
        }

        if (($values['status'] ?? null) !== null && $values['status'] !== ExpenseStatus::Active->value) {
            $context->error($line, 'status', __('Imported expenses must be active. Voided records cannot be imported.'));
        }

        foreach (['voided_at', 'voided_by', 'void_reason'] as $field) {
            if (($values[$field] ?? null) !== null) {
                $context->error($line, $field, __('Voided expense metadata is export-only.'));
            }
        }

        if ($property !== null) {
            $referenceKey = $this->referenceKey($property->id, (string) $values['reference']);

            if (isset($context->state[$referenceKey])) {
                $context->error($line, 'reference', __('Another row uses the same expense reference in this property.'));
            } else {
                $context->state[$referenceKey] = $line;
            }

            if (Expense::query()
                ->where('property_id', $property->id)
                ->whereRaw('lower(trim(reference)) = ?', [mb_strtolower((string) $values['reference'])])
                ->exists()) {
                $context->error($line, 'reference', __('An expense with this reference already exists in the property. Imports create new records only.'));
            }
        }

        if ($context->errorsSince($rowErrorCount)) {
            return null;
        }

        return [
            'property_id' => $property->id,
            'expense_category_id' => $category->id,
            'reference' => $values['reference'],
            'amount' => $this->moneyConverter->normalizeAmount((string) $values['amount'], (string) $values['currency']),
            'currency' => $this->moneyConverter->normalizeCurrency((string) $values['currency']),
            'expense_date' => $values['expense_date'],
            'vendor' => $values['vendor'] ?? null,
            'description' => $values['description'] ?? null,
            'notes' => $values['notes'] ?? null,
            'status' => ExpenseStatus::Active->value,
        ];
    }

    /**
     * Lock referenced properties before checking duplicate references so concurrent
     * expense imports for the same property are serialized without changing the
     * nullable, non-unique manual reference semantics.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function prepareCommit(array $rows, User $actor): void
    {
        $propertyIds = collect($rows)
            ->pluck('property_id')
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($propertyIds->isEmpty()) {
            return;
        }

        $properties = Property::query()
            ->whereKey($propertyIds->all())
            ->whereNull('properties.deleted_at')
            ->where('is_active', true)
            ->when(! $actor->isOwner(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $users) => $users->whereKey($actor->id),
            ))
            ->orderBy('properties.id')
            ->lockForUpdate()
            ->pluck('properties.id');

        if ($properties->count() !== $propertyIds->count()) {
            throw new ImportCommitException(
                __('A referenced property was deleted, deactivated, or is no longer accessible. No records were imported. Preview the file again and retry.'),
            );
        }

        $categoryIds = collect($rows)
            ->pluck('expense_category_id')
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();
        $categories = ExpenseCategory::query()
            ->whereKey($categoryIds->all())
            ->where('is_active', true)
            ->orderBy('expense_categories.id')
            ->lockForUpdate()
            ->pluck('expense_categories.id');

        if ($categories->count() !== $categoryIds->count()) {
            throw new ImportCommitException(
                __('A referenced expense category was deactivated or changed. No records were imported. Preview the file again and retry.'),
            );
        }

        $referencesByProperty = collect($rows)->groupBy('property_id')->map(
            fn (Collection $propertyRows): Collection => $propertyRows
                ->pluck('reference')
                ->map(fn (mixed $reference): string => mb_strtolower(trim((string) $reference)))
                ->unique()
                ->values(),
        );

        foreach ($referencesByProperty as $propertyId => $references) {
            $propertyRows = collect($rows)->where('property_id', (int) $propertyId);

            if ($propertyRows->count() !== $references->count()) {
                throw new ImportCommitException(
                    __('The import contains a duplicate expense reference in one of the target properties. No records were imported. Preview the file again and retry.'),
                );
            }

            $existingReferences = Expense::query()
                ->where('property_id', (int) $propertyId)
                ->whereIn(DB::raw('lower(trim(reference))'), $references->all())
                ->pluck('reference')
                ->map(fn (string $reference): string => mb_strtolower($reference));

            if ($existingReferences->isNotEmpty()) {
                throw new ImportCommitException(
                    __('An expense reference already exists in one of the target properties. No records were imported. Preview the file again and retry.'),
                );
            }
        }
    }

    public function persist(array $row, User $actor): Model
    {
        return Expense::create($row);
    }

    public function exportQuery(User $actor, array $filters): Builder
    {
        $status = $this->filterValues($filters, 'status');
        $currencies = $this->filterValues($filters, 'currency');
        $includeVoided = (bool) ($filters['include_archived'] ?? false)
            || in_array(ExpenseStatus::Voided->value, $status, true);

        return Expense::query()
            ->with([
                'property:id,slug,name',
                'category:id,slug,label',
            ])
            ->whereHas('property', function (Builder $property) use ($actor): void {
                $property->when(! $actor->isOwner(), fn (Builder $query) => $query->whereHas(
                    'users',
                    fn (Builder $users) => $users->whereKey($actor->id),
                ));
            })
            ->when(! $includeVoided, fn (Builder $query) => $query->where('expenses.status', ExpenseStatus::Active->value))
            ->when($status !== [], function (Builder $query) use ($status): void {
                if (array_diff($status, ExpenseStatus::values()) !== []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereIn('expenses.status', $status);
            })
            ->when($currencies !== [], fn (Builder $query) => $query->whereIn('expenses.currency', $currencies))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = mb_strtolower($search);

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereRaw('lower(expenses.vendor) like ?', ['%'.$search.'%'])
                        ->orWhereRaw('lower(expenses.reference) like ?', ['%'.$search.'%'])
                        ->orWhereHas('property', fn (Builder $property) => $property->whereRaw('lower(name) like ?', ['%'.$search.'%']))
                        ->orWhereHas('category', fn (Builder $category) => $category->whereRaw('lower(label) like ?', ['%'.$search.'%']));
                });
            })
            ->orderBy('expenses.expense_date')
            ->orderBy('expenses.id');
    }

    public function exportRow(Model $model, bool $sensitive = false): array
    {
        /** @var Expense $expense */
        $expense = $model;

        return [
            $expense->property?->slug,
            $expense->category?->slug,
            $expense->reference,
            $expense->amount,
            $expense->currency,
            $expense->expense_date?->format('Y-m-d'),
            $expense->vendor,
            $expense->description,
            $expense->notes,
            $expense->status?->value ?? $expense->status,
            $expense->voided_at?->toIso8601String(),
            $expense->voided_by,
            $expense->void_reason,
        ];
    }

    private function referenceKey(int $propertyId, string $reference): string
    {
        return 'expense-reference|'.$propertyId.'|'.Str::lower(trim($reference));
    }
}
