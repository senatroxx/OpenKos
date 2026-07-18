<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return $user->properties->contains($payment->invoice->lease->unit->property_id);
    }

    public function create(User $user, Lease $lease): bool
    {
        if (! $user->can('payments.create')) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        return $user->properties->contains($lease->unit->property_id);
    }

    public function verify(User $user, Payment $payment): bool
    {
        if (! $user->can('payments.verify')) {
            return false;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            return false;
        }

        return $user->isOwner() || $user->properties->contains($payment->invoice->lease->unit->property_id);
    }
}
