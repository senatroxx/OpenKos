<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('expenses.view')
            && $user->canAccessProperty($expense->property_id);
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return ! $expense->isVoided()
            && $user->can('expenses.update')
            && $user->canAccessProperty($expense->property_id);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return ! $expense->isVoided()
            && $user->can('expenses.delete')
            && $user->canAccessProperty($expense->property_id);
    }
}
