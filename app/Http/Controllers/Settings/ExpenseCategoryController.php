<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreExpenseCategoryRequest;
use App\Http\Requests\Settings\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/expense-categories', [
            'categories' => ExpenseCategory::ordered()
                ->withCount(['expenses', 'recurringExpenses'])
                ->get(['id', 'slug', 'label', 'is_active', 'sort_order']),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        ExpenseCategory::create([
            'slug' => $this->uniqueSlug($data['label']),
            'label' => $data['label'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => (int) ExpenseCategory::max('sort_order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense category added.')]);

        return back();
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense category updated.')]);

        return back();
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        if ($expenseCategory->expenses()->exists() || $expenseCategory->recurringExpenses()->exists()) {
            $expenseCategory->update(['is_active' => false]);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense category archived.')]);

            return back();
        }

        $expenseCategory->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense category deleted.')]);

        return back();
    }

    private function uniqueSlug(string $label): string
    {
        $base = Str::slug($label, '_');
        $slug = $base;
        $counter = 1;

        while (ExpenseCategory::where('slug', $slug)->exists()) {
            $slug = $base.'_'.++$counter;
        }

        return $slug;
    }
}
