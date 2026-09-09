<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseStatus;
use App\Http\Requests\Expense\IndexExpenseRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Requests\Expense\VoidExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Media;
use App\Models\Property;
use App\Services\Media\MediaManager;
use App\Services\Payments\MoneyConverter;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(IndexExpenseRequest $request, MoneyConverter $moneyConverter): Response
    {
        $table = Table::make()
            ->columns([
                Column::make('expense_date', 'Date')->sortable(),
                Column::make('property_name', 'Property')
                    ->sortable(fn (Builder $query, string $direction) => $query->orderBy(
                        Property::select('name')->whereColumn('properties.id', 'expenses.property_id'),
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
                        ExpenseCategory::select('label')->whereColumn('expense_categories.id', 'expenses.expense_category_id'),
                        $direction,
                    ))
                    ->searchable(fn (Builder $query, string $search) => $query->orWhereHas(
                        'category',
                        fn (Builder $categoryQuery) => $categoryQuery->whereRaw(
                            'lower(label) like ?',
                            ['%'.mb_strtolower($search).'%'],
                        ),
                    )),
                Column::make('vendor', 'Vendor')->searchable(
                    fn (Builder $query, string $search) => $query
                        ->orWhereRaw('lower(vendor) like ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('lower(reference) like ?', ['%'.mb_strtolower($search).'%']),
                ),
                Column::make('amount', 'Amount')->sortable(),
                Column::make('status', 'Status')->sortable(),
            ])
            ->filters([
                Filter::select('property_id', 'Property', function () use ($request): array {
                    return Property::query()
                        ->when(! $request->user()->isOwner(), fn (Builder $query) => $query->whereHas(
                            'users',
                            fn (Builder $userQuery) => $userQuery->whereKey($request->user()->id),
                        ))
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (Property $property): array => [
                            'value' => (string) $property->id,
                            'label' => $property->name,
                        ])
                        ->all();
                })->query(fn (Builder $query, string $value) => $query->where('property_id', $value)),
                Filter::select('category_id', 'Category', fn (): array => ExpenseCategory::ordered()
                    ->get(['id', 'label'])
                    ->map(fn (ExpenseCategory $category): array => [
                        'value' => (string) $category->id,
                        'label' => $category->label,
                    ])
                    ->all())->query(fn (Builder $query, string $value) => $query->where('expense_category_id', $value)),
                Filter::select('currency', 'Currency', fn (): array => Expense::query()
                    ->distinct()
                    ->orderBy('currency')
                    ->pluck('currency')
                    ->all())->query(fn (Builder $query, string $value) => $query->where('currency', $value)),
                Filter::select('status', 'Status', [
                    ExpenseStatus::Active->value,
                    ExpenseStatus::Voided->value,
                ])->query(fn (Builder $query, string $value) => $query->where('status', $value)),
            ])
            ->defaultSort('-expense_date');

        $statusValues = is_string($request->query('status'))
            ? array_values(array_intersect(
                explode(',', $request->query('status')),
                ExpenseStatus::values(),
            ))
            : [];
        $statusValues = $statusValues === [] ? [ExpenseStatus::Active->value] : $statusValues;
        $status = implode(',', $statusValues);
        $query = Expense::query()
            ->with([
                'property:id,name',
                'category:id,slug,label,is_active',
                'voidedByUser:id,name',
                'media' => fn ($mediaQuery) => $mediaQuery
                    ->where('collection', 'receipts')
                    ->select(['id', 'mediable_type', 'mediable_id', 'collection', 'mime_type', 'size', 'original_name']),
            ])
            ->whereIn('status', $statusValues)
            ->when(! $request->user()->isOwner(), fn (Builder $query) => $query->whereHas(
                'property.users',
                fn (Builder $userQuery) => $userQuery->whereKey($request->user()->id),
            ))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('expense_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('expense_date', '<=', $request->string('date_to')->toString()));

        $result = $table->paginate($query, $request, 'expenses');

        foreach ($result['expenses'] as $expense) {
            $receipt = $expense->media->first();
            $expense->setAttribute('receipt', $receipt ? [
                'id' => $receipt->id,
                'original_name' => $receipt->original_name,
                'mime_type' => $receipt->mime_type,
                'size' => $receipt->size,
                'download_url' => route('expenses.receipt', $expense),
            ] : null);
            $expense->makeHidden('media');
        }

        $properties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $userQuery) => $userQuery->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('expenses/index', [
            ...$result,
            'properties' => $properties,
            'categories' => ExpenseCategory::ordered()->get(['id', 'slug', 'label', 'is_active']),
            'currencies' => array_keys($moneyConverter->scales()),
            'status' => $status,
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
            'can' => [
                'create' => $request->user()->can('expenses.create'),
                'update' => $request->user()->can('expenses.update'),
                'delete' => $request->user()->can('expenses.delete'),
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request, MediaManager $mediaManager): RedirectResponse
    {
        $data = $request->validated();
        $receipt = $request->file('receipt');
        unset($data['receipt']);

        $expense = DB::transaction(function () use ($data, $receipt, $mediaManager): Expense {
            $expense = Expense::create($data);

            if ($receipt !== null) {
                $mediaManager->store($expense, 'receipts', $receipt);
            }

            return $expense;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense recorded.')]);

        return back();
    }

    public function update(UpdateExpenseRequest $request, Expense $expense, MediaManager $mediaManager): RedirectResponse
    {
        abort_if($expense->isVoided(), 403);
        $this->authorize('update', $expense);

        $data = $request->validated();
        $receipt = $request->file('receipt');
        $removeReceipt = (bool) ($data['remove_receipt'] ?? false);
        unset($data['receipt'], $data['remove_receipt']);

        DB::transaction(function () use ($expense, $data, $receipt, $removeReceipt, $mediaManager): void {
            $expense->update($data);
            $currentReceipt = $expense->media()->where('collection', 'receipts')->first();

            if ($receipt !== null && $currentReceipt instanceof Media) {
                $mediaManager->replace($currentReceipt, $receipt);
            } elseif ($receipt !== null) {
                $mediaManager->store($expense, 'receipts', $receipt);
            } elseif ($removeReceipt && $currentReceipt instanceof Media) {
                $mediaManager->remove($currentReceipt);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

        return back();
    }

    public function destroy(VoidExpenseRequest $request, Expense $expense): RedirectResponse
    {
        abort_if($expense->isVoided(), 403);
        $this->authorize('delete', $expense);

        $expense->update([
            'status' => ExpenseStatus::Voided,
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $request->validated('reason'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense voided.')]);

        return back();
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        $this->authorize('view', $expense);

        $media = $expense->media()->where('collection', 'receipts')->firstOrFail();
        $storage = Storage::disk($media->disk);
        abort_unless($storage->exists($media->path), 404);

        return $storage->response($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
        ]);
    }
}
