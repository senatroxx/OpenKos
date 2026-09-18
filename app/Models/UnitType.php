<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\SerializesDatesWithTimezone;
use App\Services\Payments\MoneyConverter;
use Database\Factories\UnitTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    use Auditable, HasFactory, HasMedia, SerializesDatesWithTimezone, SoftDeletes;

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

    public function rates(): HasMany
    {
        return $this->hasMany(UnitTypeRate::class);
    }

    public function activeRates(): HasMany
    {
        return $this->hasMany(UnitTypeRate::class)
            ->where('is_active', true)
            ->orderByRaw("case billing_unit when 'day' then 1 when 'week' then 2 when 'month' then 3 when 'year' then 4 else 5 end")
            ->orderBy('billing_interval')
            ->orderBy('id');
    }

    public function defaultActiveRate(?string $currency = null): ?UnitTypeRate
    {
        $preferredCurrency = app(MoneyConverter::class)->normalizeCurrency($currency);
        $rates = $this->relationLoaded('activeRates') ? $this->activeRates : $this->activeRates()->get();

        return $rates->first(fn (UnitTypeRate $rate): bool => $rate->currency === $preferredCurrency)
            ?? $rates->first();
    }

    public function scopeViablePublicOffering(Builder $query): void
    {
        $query
            ->configuredForPublicOffering()
            ->where('is_published', true)
            ->whereNotNull('public_slug')
            ->where('public_slug', '<>', '');
    }

    public function scopeConfiguredForPublicOffering(Builder $query): void
    {
        $query
            ->where('is_active', true)
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
