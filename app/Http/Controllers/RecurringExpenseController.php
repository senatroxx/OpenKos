<?php

namespace App\Http\Controllers;

use App\Enums\BillingUnit;
use App\Http\Requests\RecurringExpense\IndexRecurringExpenseRequest;
use App\Http\Requests\RecurringExpense\StoreRecurringExpenseRequest;
use App\Http\Requests\RecurringExpense\UpdateRecurringExpenseRequest;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\RecurringExpense;
use App\Services\Payments\MoneyConverter;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RecurringExpenseController extends Controller
{
    public function index(IndexRecurringExpenseRequest $request, MoneyConverter $moneyConverter): Response
    {
        $today = CarbonImmutable::today()->toDateString();
        $table = Table::make()
            ->columns([
                Column::make('property_name', 'Property')
                    ->sortable(fn (Builder $query, string $direction) => $query->orderBy(
                        Property::select('name')->whereColumn('properties.id', 'recurring_expenses.property_id'),
                        $direction,
                    ))
                    ->searchable(fn (Builder $query, string $search) => $query->orWhereHas(
                        'property',
                        fn (Builder $propertyQuery) => $propertyQuery->whereRaw(
                            'lower(name) like ?',
                            ['%'.mb_strtolower($search).'%'],
                        ),
                    )),
                Column::make('category_name', 'Category')
                    ->sortable(fn (Builder $query, string $direction) => $query->orderBy(
                        ExpenseCategory::select('label')->whereColumn('expense_categories.id', 'recurring_expenses.expense_category_id'),
                        $direction,
                    ))
                    ->searchable(fn (Builder $query, string $search) => $query->orWhereHas(
                        'category',
                        fn (Builder $categoryQuery) => $categoryQuery->whereRaw(
                            'lower(label) like ?',
                            ['%'.mb_strtolower($search).'%'],
                        ),
                    )),
                Column::make('amount', 'Amount')->sortable(),
                Column::make('billing_unit', 'Frequency')->sortable(),
                Column::make('vendor', 'Vendor')->searchable(),
                Column::make('start_date', 'Start')->sortable(),
                Column::make('end_date', 'End')->sortable(),
                Column::make('status', 'Status'),
                Column::make('_actions', 'Actions'),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'paused', 'ended'])
                    ->query(function (Builder $query, string $value) use ($today): void {
                        match ($value) {
                            'active' => $query
                                ->where('is_active', true)
                                ->whereNotNull('next_due_on')
                                ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today)),
                            'paused' => $query
                                ->where('is_active', false)
                                ->whereNotNull('paused_at')
                                ->whereNotNull('next_due_on')
                                ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today)),
                            'ended' => $query->whereNotNull('end_date')->where(function (Builder $query) use ($today): void {
                                $query->whereDate('end_date', '<', $today)
                                    ->orWhereNull('next_due_on');
                            }),
                            default => null,
                        };
                    }),
            ])
            ->defaultSort('-start_date');

        $query = RecurringExpense::query()
            ->with(['property:id,name', 'category:id,slug,label,is_active'])
            ->when(! $request->user()->isOwner(), fn (Builder $query) => $query->whereHas(
                'property.users',
                fn (Builder $userQuery) => $userQuery->whereKey($request->user()->id),
            ));

        $result = $table->paginate($query, $request, 'recurring_expenses');

        foreach ($result['recurring_expenses'] as $recurringExpense) {
            $recurringExpense->setAttribute('status', $this->status($recurringExpense));
        }

        $properties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $userQuery) => $userQuery->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('expenses/recurring', [
            ...$result,
            'properties' => $properties,
            'categories' => ExpenseCategory::ordered()->get(['id', 'slug', 'label', 'is_active']),
            'currencies' => array_keys($moneyConverter->scales()),
            'billing_units' => BillingUnit::values(),
            'status' => $request->query('status', ''),
            'can' => [
                'create' => $request->user()->can('expenses.create'),
                'update' => $request->user()->can('expenses.update'),
            ],
        ]);
    }

    public function store(StoreRecurringExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $recurringExpense = new RecurringExpense($data);
        $recurringExpense->next_due_on = $recurringExpense->firstOccurrence()->toDateString();
        $recurringExpense->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring expense added.')]);

        return back();
    }

    public function update(
        UpdateRecurringExpenseRequest $request,
        RecurringExpense $recurringExpense,
    ): RedirectResponse {
        $data = $request->validated();

        DB::transaction(function () use ($data, $recurringExpense): void {
            $locked = RecurringExpense::query()->lockForUpdate()->findOrFail($recurringExpense->getKey());
            $scheduleChanged = collect(['billing_interval', 'billing_unit', 'start_date', 'end_date'])
                ->contains(fn (string $field): bool => array_key_exists($field, $data)
                    && (string) $locked->getRawOriginal($field) !== (string) $data[$field]);

            $locked->fill($data);

            if ($scheduleChanged) {
                $nextDueOn = $locked->nextOccurrenceAfter(CarbonImmutable::today());
                $locked->next_due_on = $locked->end_date !== null
                    && $nextDueOn->greaterThan($locked->end_date->startOfDay())
                    ? null
                    : $nextDueOn->toDateString();
            } elseif ($locked->end_date !== null
                && $locked->next_due_on !== null
                && $locked->next_due_on->greaterThan($locked->end_date)) {
                $locked->next_due_on = null;
            }

            $locked->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring expense updated.')]);

        return back();
    }

    public function pause(RecurringExpense $recurringExpense): RedirectResponse
    {
        $this->authorize('update', $recurringExpense);

        DB::transaction(function () use ($recurringExpense): void {
            $locked = RecurringExpense::query()->lockForUpdate()->findOrFail($recurringExpense->getKey());

            if ($locked->is_active && ! $locked->isEnded()) {
                $locked->is_active = false;
                $locked->paused_at = now();
                $locked->save();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring expense paused.')]);

        return back();
    }

    public function resume(RecurringExpense $recurringExpense): RedirectResponse
    {
        $this->authorize('update', $recurringExpense);

        DB::transaction(function () use ($recurringExpense): void {
            $locked = RecurringExpense::query()->lockForUpdate()->findOrFail($recurringExpense->getKey());
            $nextDueOn = $locked->nextOccurrenceAfter(CarbonImmutable::today());

            if ($locked->end_date !== null && $nextDueOn->greaterThan($locked->end_date->startOfDay())) {
                throw ValidationException::withMessages([
                    'recurring_expense' => __('This schedule has no future occurrence. Edit its end date or cadence before resuming.'),
                ]);
            }

            $locked->update([
                'is_active' => true,
                'paused_at' => null,
                'next_due_on' => $nextDueOn->toDateString(),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring expense resumed.')]);

        return back();
    }

    private function status(RecurringExpense $recurringExpense): string
    {
        if ($recurringExpense->end_date !== null
            && ($recurringExpense->end_date->lt(CarbonImmutable::today())
                || $recurringExpense->next_due_on === null)) {
            return 'ended';
        }

        return $recurringExpense->is_active ? 'active' : 'paused';
    }
}
