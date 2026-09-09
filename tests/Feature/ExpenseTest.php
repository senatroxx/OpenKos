<?php

use App\Enums\ExpenseStatus;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('requires the expenses view permission', function (): void {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('expenses.index'))
        ->assertForbidden();
});

it('lists expenses for the owner with the configured table metadata', function (): void {
    $owner = User::factory()->owner()->create();
    $expense = Expense::factory()->create();

    $this->actingAs($owner)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('expenses/index')
            ->has('expenses.data', 1)
            ->where('expenses.data.0.id', $expense->id)
            ->has('table.filters', 4)
            ->has('categories'));

    $this->actingAs($owner)
        ->get(route('expenses.index', ['search' => $expense->reference]))
        ->assertInertia(fn ($page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.id', $expense->id));
});

it('creates an expense with a currency snapshot', function (): void {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $category = ExpenseCategory::factory()->create();

    $this->actingAs($owner)
        ->post(route('expenses.store'), [
            'property_id' => $property->id,
            'expense_category_id' => $category->id,
            'amount' => '125.50',
            'currency' => 'USD',
            'expense_date' => '2026-09-01',
            'vendor' => 'Water Company',
            'reference' => 'INV-123',
        ])
        ->assertRedirect();

    $expense = Expense::query()->latest('id')->firstOrFail();

    expect($expense->property_id)->toBe($property->id)
        ->and($expense->expense_category_id)->toBe($category->id)
        ->and($expense->amount)->toBe('125.500')
        ->and($expense->currency)->toBe('USD')
        ->and($expense->status)->toBe(ExpenseStatus::Active);
});

it('uses the existing currency minor-unit validation', function (): void {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('expenses.store'), [
            'property_id' => Property::factory()->create()->id,
            'expense_category_id' => ExpenseCategory::factory()->create()->id,
            'amount' => '1.1',
            'currency' => 'IDR',
            'expense_date' => '2026-09-01',
        ])
        ->assertSessionHasErrors('amount');
});

it('limits non-owner expenses to assigned properties', function (): void {
    $admin = User::factory()->admin()->create();
    $assigned = Property::factory()->create();
    $unassigned = Property::factory()->create();
    $assigned->users()->attach($admin);

    Expense::factory()->create(['property_id' => $assigned->id]);
    Expense::factory()->create(['property_id' => $unassigned->id]);

    $this->actingAs($admin)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.property_id', $assigned->id));

    $this->actingAs($admin)
        ->post(route('expenses.store'), [
            'property_id' => $unassigned->id,
            'expense_category_id' => ExpenseCategory::factory()->create()->id,
            'amount' => '10.00',
            'currency' => 'USD',
            'expense_date' => '2026-09-01',
        ])
        ->assertSessionHasErrors('property_id');
});

it('keeps the currency snapshot immutable', function (): void {
    $expense = Expense::factory()->create(['currency' => 'USD']);

    expect(fn () => $expense->update(['currency' => 'EUR']))
        ->toThrow(LogicException::class);
});

it('voids an expense with an audited reason and makes it read-only', function (): void {
    $owner = User::factory()->owner()->create();
    $expense = Expense::factory()->create();
    AuditLog::query()->delete();

    $this->actingAs($owner)
        ->delete(route('expenses.destroy', $expense), ['reason' => 'Duplicate entry'])
        ->assertRedirect();

    $expense->refresh();

    expect($expense->status)->toBe(ExpenseStatus::Voided)
        ->and($expense->voided_at)->not->toBeNull()
        ->and($expense->voided_by)->toBe($owner->id)
        ->and($expense->void_reason)->toBe('Duplicate entry');

    expect(AuditLog::query()
        ->where('auditable_type', $expense->getMorphClass())
        ->where('auditable_id', $expense->id)
        ->where('operation', 'update')
        ->latest('id')
        ->firstOrFail()
        ->after)
        ->toMatchArray([
            'status' => ExpenseStatus::Voided->value,
            'void_reason' => 'Duplicate entry',
        ]);

    $this->actingAs($owner)
        ->put(route('expenses.update', $expense), [
            'property_id' => $expense->property_id,
            'expense_category_id' => $expense->expense_category_id,
            'amount' => '20.00',
            'expense_date' => '2026-09-01',
        ])
        ->assertForbidden();

    expect(fn () => $expense->update(['notes' => 'Attempted change']))
        ->toThrow(LogicException::class);
});

it('supports date range and status filters', function (): void {
    $owner = User::factory()->owner()->create();
    Expense::factory()->create(['expense_date' => '2026-01-01']);
    Expense::factory()->voided()->create(['expense_date' => '2026-02-01']);

    $this->actingAs($owner)
        ->get(route('expenses.index', [
            'date_from' => '2026-01-15',
            'date_to' => '2026-02-15',
            'status' => 'voided',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.status', ExpenseStatus::Voided->value));

    $this->actingAs($owner)
        ->get(route('expenses.index', ['status' => 'active,voided']))
        ->assertInertia(fn ($page) => $page
            ->has('expenses.data', 2)
            ->where('status', 'active,voided'));
});

it('stores one optional receipt and exposes its download route', function (): void {
    Storage::fake('local');
    $owner = User::factory()->owner()->create();
    $expense = Expense::factory()->create();

    $this->actingAs($owner)
        ->put(route('expenses.update', $expense), [
            'property_id' => $expense->property_id,
            'expense_category_id' => $expense->expense_category_id,
            'amount' => '125.00',
            'expense_date' => '2026-09-01',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($expense->media()->where('collection', 'receipts')->count())->toBe(1);
    expect(route('expenses.receipt', $expense))->toContain('/expenses/'.$expense->id.'/receipt');
});
