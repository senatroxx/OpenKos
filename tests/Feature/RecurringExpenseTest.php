<?php

use App\Actions\Expenses\GenerateRecurringExpenses;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\RecurringExpense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
});

function makeRecurringExpense(array $overrides = []): RecurringExpense
{
    $startDate = $overrides['start_date'] ?? '2026-01-01';

    return RecurringExpense::factory()->create(array_merge([
        'start_date' => $startDate,
        'next_due_on' => $startDate,
    ], $overrides));
}

it('anchors monthly recurrence to the original day and clamps January 31 in February', function (): void {
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2024-01-31',
        'next_due_on' => '2024-01-31',
        'billing_unit' => 'month',
    ]);

    expect($recurringExpense->nextOccurrenceAfter('2024-01-31')->toDateString())
        ->toBe('2024-02-29')
        ->and($recurringExpense->nextOccurrenceAfter('2024-02-29')->toDateString())
        ->toBe('2024-03-31')
        ->and($recurringExpense->nextOccurrenceAfter('2025-02-28')->toDateString())
        ->toBe('2025-03-31');
});

it('recovers February 29 on the next leap year for yearly recurrence', function (): void {
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2024-02-29',
        'next_due_on' => '2024-02-29',
        'billing_unit' => 'year',
    ]);

    expect($recurringExpense->nextOccurrenceAfter('2024-02-29')->toDateString())
        ->toBe('2025-02-28')
        ->and($recurringExpense->nextOccurrenceAfter('2027-02-28')->toDateString())
        ->toBe('2028-02-29');
});

it('catches up active schedules after scheduler downtime', function (): void {
    $this->travelTo('2026-09-20');
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2026-06-01',
        'next_due_on' => '2026-06-01',
        'billing_unit' => 'month',
    ]);

    $count = app(GenerateRecurringExpenses::class)->execute();

    expect($count)->toBe(4)
        ->and($recurringExpense->expenses()->count())->toBe(4)
        ->and($recurringExpense->refresh()->next_due_on->toDateString())->toBe('2026-10-01');
});

it('does not generate occurrences while paused', function (): void {
    $this->travelTo('2026-09-20');
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2026-09-01',
        'next_due_on' => '2026-09-01',
        'is_active' => false,
        'paused_at' => now(),
    ]);

    expect(app(GenerateRecurringExpenses::class)->execute())->toBe(0)
        ->and($recurringExpense->expenses()->count())->toBe(0)
        ->and($recurringExpense->refresh()->next_due_on->toDateString())->toBe('2026-09-01');
});

it('limits a large catch-up batch while continuing to advance the cursor', function (): void {
    $this->travelTo('2029-01-01');
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2026-01-01',
        'next_due_on' => '2026-01-01',
        'billing_unit' => 'day',
    ]);

    expect(app(GenerateRecurringExpenses::class)->execute())->toBe(1000)
        ->and($recurringExpense->expenses()->count())->toBe(1000)
        ->and($recurringExpense->refresh()->next_due_on->toDateString())
        ->toBe(CarbonImmutable::parse('2026-01-01')->addDays(1000)->toDateString());
});

it('skips paused occurrences and resumes strictly from the next future occurrence', function (): void {
    $owner = User::factory()->owner()->create();
    $this->travelTo('2026-06-10');
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2026-06-01',
        'next_due_on' => '2026-06-01',
        'billing_unit' => 'month',
    ]);

    $this->actingAs($owner)
        ->post(route('expenses.recurring.pause', $recurringExpense))
        ->assertRedirect();

    $this->travelTo('2026-08-20');

    $this->actingAs($owner)
        ->post(route('expenses.recurring.resume', $recurringExpense->fresh()))
        ->assertRedirect();

    expect($recurringExpense->refresh()->next_due_on->toDateString())->toBe('2026-09-01')
        ->and($recurringExpense->expenses()->count())->toBe(0);

    app(GenerateRecurringExpenses::class)->execute();

    expect($recurringExpense->expenses()->count())->toBe(0);

    $this->travelTo('2026-09-02');
    app(GenerateRecurringExpenses::class)->execute();

    expect($recurringExpense->expenses()->whereDate('expense_date', '2026-09-01')->exists())->toBeTrue();
});

it('does not backfill historical occurrences after a cadence or start-date edit', function (): void {
    $owner = User::factory()->owner()->create();
    $newProperty = Property::factory()->create();
    $newCategory = ExpenseCategory::factory()->create();
    $this->travelTo('2026-01-02');
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2026-01-01',
        'next_due_on' => '2026-01-01',
        'amount' => '500000.00',
    ]);

    app(GenerateRecurringExpenses::class)->execute();
    $generated = $recurringExpense->expenses()->firstOrFail();

    $this->travelTo('2026-02-02');
    $this->actingAs($owner)
        ->put(route('expenses.recurring.update', $recurringExpense), [
            'property_id' => $newProperty->id,
            'expense_category_id' => $newCategory->id,
            'amount' => '750000.00',
            'currency' => 'USD',
            'vendor' => 'New vendor',
            'description' => 'Updated description',
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'start_date' => '2026-02-15',
            'end_date' => null,
        ])
        ->assertRedirect();

    expect($generated->refresh()->amount)->toBe('500000.000')
        ->and($generated->property_id)->toBe($recurringExpense->getRawOriginal('property_id'))
        ->and($generated->expense_category_id)->toBe($recurringExpense->getRawOriginal('expense_category_id'))
        ->and($generated->currency)->toBe('IDR')
        ->and($generated->vendor)->not->toBe('New vendor')
        ->and($generated->description)->not->toBe('Updated description')
        ->and($recurringExpense->refresh()->next_due_on->toDateString())->toBe('2026-02-15');

    $this->travelTo('2026-02-20');
    app(GenerateRecurringExpenses::class)->execute();

    $futureExpense = $recurringExpense->expenses()->whereDate('expense_date', '2026-02-15')->firstOrFail();

    expect($futureExpense->amount)->toBe('750000.000')
        ->and($futureExpense->property_id)->toBe($newProperty->id)
        ->and($futureExpense->expense_category_id)->toBe($newCategory->id)
        ->and($futureExpense->currency)->toBe('USD')
        ->and($futureExpense->vendor)->toBe('New vendor')
        ->and($futureExpense->description)->toBe('Updated description');
});

it('generates an occurrence on the inclusive end date and then ends', function (): void {
    $this->travelTo('2026-03-15');
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-01',
        'next_due_on' => '2026-03-01',
    ]);

    expect(app(GenerateRecurringExpenses::class)->execute())->toBe(1)
        ->and($recurringExpense->expenses()->whereDate('expense_date', '2026-03-01')->exists())->toBeTrue()
        ->and($recurringExpense->refresh()->next_due_on)->toBeNull();
});

it('clears the cursor when an edited end date leaves no future occurrence', function (): void {
    $owner = User::factory()->owner()->create();
    $this->travelTo('2026-09-20');
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2026-01-01',
        'next_due_on' => '2026-10-01',
        'end_date' => null,
    ]);

    $this->actingAs($owner)
        ->put(route('expenses.recurring.update', $recurringExpense), [
            'property_id' => $recurringExpense->property_id,
            'expense_category_id' => $recurringExpense->expense_category_id,
            'amount' => $recurringExpense->amount,
            'currency' => $recurringExpense->currency,
            'vendor' => $recurringExpense->vendor,
            'description' => $recurringExpense->description,
            'billing_interval' => $recurringExpense->billing_interval,
            'billing_unit' => $recurringExpense->billing_unit->value,
            'start_date' => $recurringExpense->start_date->toDateString(),
            'end_date' => '2026-09-10',
        ])
        ->assertRedirect();

    expect($recurringExpense->refresh()->next_due_on)->toBeNull();
});

it('rejects resuming a schedule with no future occurrence', function (): void {
    $owner = User::factory()->owner()->create();
    $this->travelTo('2026-09-20');
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2026-01-01',
        'end_date' => '2026-09-10',
    ]);
    $recurringExpense->update([
        'is_active' => false,
        'paused_at' => now(),
        'next_due_on' => null,
    ]);

    $this->actingAs($owner)
        ->post(route('expenses.recurring.resume', $recurringExpense))
        ->assertSessionHasErrors('recurring_expense');

    expect($recurringExpense->refresh()->is_active)->toBeFalse();
});

it('keeps a voided generated expense from being regenerated', function (): void {
    $this->travelTo('2026-09-02');
    $recurringExpense = makeRecurringExpense([
        'start_date' => '2026-09-01',
        'next_due_on' => '2026-09-01',
    ]);
    $expense = Expense::factory()->voided()->create([
        'recurring_expense_id' => $recurringExpense->id,
        'property_id' => $recurringExpense->property_id,
        'expense_category_id' => $recurringExpense->expense_category_id,
        'expense_date' => '2026-09-01',
    ]);

    expect(app(GenerateRecurringExpenses::class)->execute())->toBe(0)
        ->and($recurringExpense->expenses()->count())->toBe(1)
        ->and($expense->refresh()->status)->toBe(ExpenseStatus::Voided);
});

it('shows recurring expense definitions only for accessible properties', function (): void {
    $admin = User::factory()->admin()->create();
    $assigned = Property::factory()->create();
    $unassigned = Property::factory()->create();
    $assigned->users()->attach($admin);
    $category = ExpenseCategory::factory()->create();
    makeRecurringExpense(['property_id' => $assigned->id, 'expense_category_id' => $category->id]);
    makeRecurringExpense(['property_id' => $unassigned->id, 'expense_category_id' => $category->id]);

    $this->actingAs($admin)
        ->get(route('expenses.recurring.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('recurring_expenses.data', 1));
});
