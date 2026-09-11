<?php

namespace App\Models;

use App\Enums\AmenityScope;
use Database\Factories\AmenityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'owner_property_id',
    'name',
    'scope',
    'is_active',
])]
class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'owner_property_id' => 'integer',
            'scope' => AmenityScope::class,
            'is_active' => 'boolean',
        ];
    }

    public function ownerProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'owner_property_id');
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
