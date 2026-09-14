<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RecurringExpense;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitType;
use App\Policies\ExpensePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LeasePolicy;
use App\Policies\MaintenanceTicketPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\RecurringExpensePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UnitTypePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Property::class => PropertyPolicy::class,
        Unit::class => UnitPolicy::class,
        UnitType::class => UnitTypePolicy::class,
        Tenant::class => TenantPolicy::class,
        Lease::class => LeasePolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
        Expense::class => ExpensePolicy::class,
        RecurringExpense::class => RecurringExpensePolicy::class,
        MaintenanceTicket::class => MaintenanceTicketPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            return $user->hasRole(Role::Owner->value) ? true : null;
        });
    }
}
