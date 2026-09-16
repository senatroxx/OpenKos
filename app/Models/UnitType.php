<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\SerializesDatesWithTimezone;
use Database\Factories\UnitTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'property_id',
    'name',
    'public_slug',
    'description',
    'bedrooms',
    'bathrooms',
    'size_sqm',
    'furnishing',
    'is_active',
    'is_published',
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
            'is_published' => 'boolean',
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

    public function scopeViablePublicOffering(Builder $query): void
    {
        $query
            ->where('is_active', true)
            ->where('is_published', true)
            ->whereNotNull('public_slug')
            ->where('public_slug', '<>', '')
            ->whereHas('units', fn (Builder $query) => $query->eligibleForPublicOffering());
    }

    public function isViablePublicOffering(): bool
    {
        return static::query()
            ->whereKey($this->id)
            ->viablePublicOffering()
            ->exists();
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_unit_type')
            ->using(AmenityUnitType::class)
            ->withTimestamps();
    }
}
