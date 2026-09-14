<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('is owner-only', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('settings.expense-categories.index'))
        ->assertForbidden();
});

it('lists the idempotent default categories', function (): void {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('settings.expense-categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/expense-categories')
            ->has('categories', 8));
});

it('creates a category with a generated slug', function (): void {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('settings.expense-categories.store'), [
        'label' => 'Landscaping',
    ]);

    expect(ExpenseCategory::where('slug', 'landscaping')
        ->where('label', 'Landscaping')
        ->exists())->toBeTrue();
});

it('updates a category without changing its slug', function (): void {
    $owner = User::factory()->owner()->create();
    $category = ExpenseCategory::where('slug', 'other')->firstOrFail();

    $this->actingAs($owner)->patch(route('settings.expense-categories.update', $category), [
        'label' => 'Miscellaneous',
        'is_active' => false,
    ]);

    $category->refresh();

    expect($category->slug)->toBe('other')
        ->and($category->label)->toBe('Miscellaneous')
        ->and($category->is_active)->toBeFalse();
});

it('archives a category referenced by an expense', function (): void {
    $owner = User::factory()->owner()->create();
    $category = ExpenseCategory::factory()->create();
    $expense = Expense::factory()->create(['expense_category_id' => $category->id]);

    $this->actingAs($owner)
        ->delete(route('settings.expense-categories.destroy', $category))
        ->assertRedirect();

    expect($category->refresh()->is_active)->toBeFalse()
        ->and($expense->refresh()->expense_category_id)->toBe($category->id);
});

it('archives a category referenced by a recurring expense', function (): void {
    $owner = User::factory()->owner()->create();
    $category = ExpenseCategory::factory()->create();
    RecurringExpense::factory()->create(['expense_category_id' => $category->id]);

    $this->actingAs($owner)
        ->delete(route('settings.expense-categories.destroy', $category))
        ->assertRedirect();

    expect($category->refresh()->is_active)->toBeFalse();
});

it('deletes an unused category', function (): void {
    $owner = User::factory()->owner()->create();
    $category = ExpenseCategory::factory()->create();

    $this->actingAs($owner)
        ->delete(route('settings.expense-categories.destroy', $category));

    expect(ExpenseCategory::find($category->id))->toBeNull();
});
