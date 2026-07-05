<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user-managed property classification (kos, apartment, riad, …). The `slug`
 * is a stable identifier stored on properties.type; the `label` is editable.
 */
#[Fillable([
    'slug',
    'label',
    'is_active',
    'sort_order',
])]
class PropertyType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'type', 'slug');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('label');
    }
}
