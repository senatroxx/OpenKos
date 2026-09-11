<?php

namespace App\Policies;

use App\Models\RecurringExpense;
use App\Models\User;

class RecurringExpensePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RecurringExpense $recurringExpense): bool
    {
        return $user->can('expenses.view')
            && $user->canAccessProperty($recurringExpense->property_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $user->can('expenses.update')
            && $user->canAccessProperty($recurringExpense->property_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RecurringExpense $recurringExpense): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RecurringExpense $recurringExpense): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RecurringExpense $recurringExpense): bool
    {
        return false;
    }
}
