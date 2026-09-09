<?php

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Collection;

uses()->beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createFinancialLease(Property $property, string $currency = 'USD'): Lease
{
    $unit = Unit::factory()->for($property)->create([
        'status' => UnitStatus::Occupied,
    ]);

    return Lease::factory()->for($unit)->create([
        'currency' => $currency,
        'rent_amount' => '1000.000',
        'status' => LeaseStatus::Active,
        'start_date' => now()->subYear(),
    ]);
}

function createFinancialInvoice(
    Lease $lease,
    string $periodStart,
    string $total,
    string $amountPaid,
    InvoiceStatus $status,
): Invoice {
    return Invoice::factory()->for($lease)->create([
        'currency' => $lease->currency,
        'period_start' => $periodStart,
        'period_end' => Carbon::parse($periodStart)->endOfMonth()->toDateString(),
        'due_date' => Carbon::parse($periodStart)->addDays(4)->toDateString(),
        'total' => $total,
        'amount_paid' => $amountPaid,
        'status' => $status,
    ]);
}

function createFinancialPayment(Invoice $invoice, string $amount, string $paymentDate, PaymentStatus $status = PaymentStatus::Confirmed): Payment
{
    $payment = Payment::factory()->for($invoice)->create([
        'amount' => $amount,
        'currency' => $invoice->currency,
        'payment_date' => $paymentDate,
        'status' => $status,
    ]);

    if ($status === PaymentStatus::Confirmed) {
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
        ]);
    }

    return $payment;
}

test('financial dashboard separates accrual performance from cash attribution', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-15'));

    try {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create(['name' => 'USD Property']);
        $euroProperty = Property::factory()->create(['name' => 'EUR Property']);
        $usdLease = createFinancialLease($property);
        $eurLease = createFinancialLease($euroProperty, 'EUR');

        $currentInvoice = createFinancialInvoice($usdLease, '2026-09-01', '1000.000', '400.000', InvoiceStatus::Partial);
        createFinancialPayment($currentInvoice, '400.000', '2026-09-05');
        createFinancialPayment($currentInvoice, '100.000', '2026-09-06', PaymentStatus::Pending);

        $priorInvoice = createFinancialInvoice($usdLease, '2026-08-01', '700.000', '700.000', InvoiceStatus::Paid);
        createFinancialPayment($priorInvoice, '700.000', '2026-09-05');

        createFinancialInvoice($eurLease, '2026-09-01', '2000.000', '0.000', InvoiceStatus::Pending);
        createFinancialInvoice($usdLease, '2026-09-03', '900.000', '0.000', InvoiceStatus::Cancelled);
        createFinancialInvoice($usdLease, '2026-09-04', '800.000', '0.000', InvoiceStatus::Void);

        $category = ExpenseCategory::factory()->create();
        Expense::factory()->create([
            'property_id' => $property->id,
            'expense_category_id' => $category->id,
            'amount' => '300.000',
            'currency' => 'USD',
            'expense_date' => '2026-09-10',
            'status' => ExpenseStatus::Active,
        ]);
        Expense::factory()->voided()->create([
            'property_id' => $property->id,
            'amount' => '100.000',
            'currency' => 'USD',
            'expense_date' => '2026-09-10',
        ]);
        Expense::factory()->create([
            'property_id' => $euroProperty->id,
            'amount' => '500.000',
            'currency' => 'EUR',
            'expense_date' => '2026-09-10',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.financial'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.period', 'current_month')
                ->where('financial.overview.revenue', [
                    ['currency' => 'EUR', 'amount' => '2000'],
                    ['currency' => 'USD', 'amount' => '1000'],
                ])
                ->where('financial.overview.expenses', [
                    ['currency' => 'EUR', 'amount' => '500'],
                    ['currency' => 'USD', 'amount' => '300'],
                ])
                ->where('financial.overview.noi', [
                    ['currency' => 'EUR', 'amount' => '1500'],
                    ['currency' => 'USD', 'amount' => '700'],
                ])
                ->where('financial.collections.collected', [
                    ['currency' => 'EUR', 'amount' => '0'],
                    ['currency' => 'USD', 'amount' => '400'],
                ])
                ->where('financial.collections.outstanding', [
                    ['currency' => 'EUR', 'amount' => '2000'],
                    ['currency' => 'USD', 'amount' => '600'],
                ])
                ->where('financial.collections.collection_rate', [
                    ['currency' => 'EUR', 'rate' => '0.0'],
                    ['currency' => 'USD', 'rate' => '40.0'],
                ])
                ->where('financial.cash_flow.0.collected', [[
                    'currency' => 'USD',
                    'amount' => '1100',
                ]])
                ->where('financial.property_performance', function (Collection $rows) use ($property): bool {
                    $performance = $rows->firstWhere('id', $property->id);

                    return $performance !== null
                        && $performance['revenue'] === [['currency' => 'USD', 'amount' => '1000']]
                        && $performance['expenses'] === [['currency' => 'USD', 'amount' => '300']]
                        && $performance['noi'] === [['currency' => 'USD', 'amount' => '700']]
                        && $performance['collected'] === [['currency' => 'USD', 'amount' => '400']]
                        && $performance['outstanding'] === [['currency' => 'USD', 'amount' => '600']]
                        && $performance['collection_rate'] === [['currency' => 'USD', 'rate' => '40.0']];
                })
                ->where('financial.expense_breakdown', function (Collection $breakdown) use ($category): bool {
                    return $breakdown->contains(fn (array $entry): bool => $entry['category_id'] === $category->id
                        && $entry['amounts'] === [['currency' => 'USD', 'amount' => '300']]);
                })
                ->where('financial.trends', fn ($trends) => count($trends) === 12)
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('financial dashboard keeps ytd summaries separate from its twelve month trend', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-15'));

    try {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $lease = createFinancialLease($property);

        $januaryInvoice = createFinancialInvoice($lease, '2026-01-01', '600.000', '600.000', InvoiceStatus::Paid);
        createFinancialPayment($januaryInvoice, '600.000', '2026-09-05');
        createFinancialInvoice($lease, '2026-09-01', '1000.000', '0.000', InvoiceStatus::Pending);

        Expense::factory()->create([
            'property_id' => $property->id,
            'amount' => '100.000',
            'currency' => 'USD',
            'expense_date' => '2026-01-10',
        ]);
        Expense::factory()->create([
            'property_id' => $property->id,
            'amount' => '300.000',
            'currency' => 'USD',
            'expense_date' => '2026-09-10',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.financial', ['period' => 'ytd']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('financial.period', 'ytd')
                ->where('financial.overview.revenue', [['currency' => 'USD', 'amount' => '1600']])
                ->where('financial.overview.expenses', [['currency' => 'USD', 'amount' => '400']])
                ->where('financial.overview.noi', [['currency' => 'USD', 'amount' => '1200']])
                ->where('financial.collections.collected', [['currency' => 'USD', 'amount' => '600']])
                ->where('financial.collections.collection_rate', [['currency' => 'USD', 'rate' => '37.5']])
                ->where('financial.cash_flow', fn ($cashFlow) => count($cashFlow) === 9)
                ->where('financial.cash_flow.8.collected', [['currency' => 'USD', 'amount' => '600']])
                ->where('financial.trends', fn ($trends) => count($trends) === 12)
                ->where('financial.trends.3.month', '2026-01-01')
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('financial dashboard enforces financial permission and property scope', function (): void {
    $this->get(route('dashboard.financial'))->assertRedirect('login');

    $staff = User::factory()->staff()->create();
    $this->actingAs($staff)->get(route('dashboard.financial'))->assertForbidden();

    $user = User::factory()->admin()->create();
    $accessibleProperty = Property::factory()->create();
    $hiddenProperty = Property::factory()->create();
    $user->properties()->sync([$accessibleProperty->id]);

    $accessibleLease = createFinancialLease($accessibleProperty);
    $hiddenLease = createFinancialLease($hiddenProperty);
    createFinancialInvoice($accessibleLease, now()->startOfMonth()->toDateString(), '100.000', '0.000', InvoiceStatus::Pending);
    createFinancialInvoice($hiddenLease, now()->startOfMonth()->toDateString(), '900.000', '0.000', InvoiceStatus::Pending);

    $this->actingAs($user)
        ->get(route('dashboard.financial'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('financial.overview.revenue', [['currency' => 'USD', 'amount' => '100']])
            ->where('properties.0.id', $accessibleProperty->id)
            ->where('properties', fn ($properties) => count($properties) === 1)
        );

    $this->actingAs($user)
        ->get(route('dashboard.financial', ['property_id' => $hiddenProperty->id]))
        ->assertForbidden();
});
