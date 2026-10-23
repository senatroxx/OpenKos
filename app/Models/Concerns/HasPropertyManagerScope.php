<?php

namespace App\Models\Concerns;

use App\Models\Property;
use App\Models\PropertyManagerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasPropertyManagerScope
{
    public function scopeVisibleToManager(Builder $query, User $user): void
    {
        if ($user->isOwner() || $user->isAdmin()) {
            return;
        }

        $query->whereIn('id', PropertyManagerAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('property_id'));
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_manager_assignments')
            ->withPivot(['is_active', 'notes', 'assigned_by'])
            ->withTimestamps();
    }
}
