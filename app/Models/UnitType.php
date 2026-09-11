<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\SerializesDatesWithTimezone;
use Database\Factories\UnitTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'property_id',
    'name',
    'description',
    'bedrooms',
    'bathrooms',
    'size_sqm',
    'furnishing',
    'is_active',
])]
class UnitType extends Model
{
    /** @use HasFactory<UnitTypeFactory> */
    use Auditable, HasFactory, HasMedia, SerializesDatesWithTimezone;

    protected function casts(): array
    {
        return [
            'bedrooms' => 'integer',
            'bathrooms' => 'decimal:1',
            'size_sqm' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_unit_type')
            ->using(AmenityUnitType::class)
            ->withTimestamps();
    }
}
