<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Policies\LeasePolicy;
use App\Policies\MaintenanceTicketPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\RoomPolicy;
use App\Policies\TenantPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Property::class => PropertyPolicy::class,
        Room::class => RoomPolicy::class,
        Tenant::class => TenantPolicy::class,
        Lease::class => LeasePolicy::class,
        Payment::class => PaymentPolicy::class,
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
