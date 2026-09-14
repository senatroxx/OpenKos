<?php

namespace App\Models;

use App\Enums\AmenityScope;
use Database\Factories\AmenityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'slug',
    'icon',
    'scope',
    'is_active',
])]
class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Amenity $amenity): void {
            if ($amenity->isDirty('slug')) {
                $amenity->slug = $amenity->getOriginal('slug');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scope' => AmenityScope::class,
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForProperty(Builder $query): void
    {
        $query->whereIn('scope', [AmenityScope::Property->value, AmenityScope::Both->value]);
    }

    public function scopeForUnitType(Builder $query): void
    {
        $query->whereIn('scope', [AmenityScope::UnitType->value, AmenityScope::Both->value]);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'amenity_property')
            ->using(AmenityProperty::class)
            ->withTimestamps();
    }

    public function unitTypes(): BelongsToMany
    {
        return $this->belongsToMany(UnitType::class, 'amenity_unit_type')
            ->using(AmenityUnitType::class)
            ->withTimestamps();
    }
}
