<?php

namespace App\Policies;

use App\Models\InspectionTemplate;
use App\Models\User;

class InspectionTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InspectionTemplate $inspectionTemplate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, InspectionTemplate $inspectionTemplate): bool
    {
        return true;
    }
}
