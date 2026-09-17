<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\PropertyRentalMode;
use App\Enums\UnitStatus;
use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'type',
    'rental_mode',
    'slug',
    'public_slug',
    'address',
    'region_id',
    'city_id',
    'postal_code',
    'phone',
    'description',
    'is_active',
    'is_published',
])]
class Property extends Model
{
    use Auditable, HasFactory, HasMedia, SerializesDatesWithTimezone, SoftDeletes;

    protected array $auditableMask = ['phone'];

    protected $appends = ['type_label'];

    protected function casts(): array
    {
        return [
            'rental_mode' => PropertyRentalMode::class,
            'is_active' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'type', 'slug');
    }

    /**
     * Human-readable type name when the relation is explicitly loaded.
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn () => $this->relationLoaded('propertyType')
            ? $this->propertyType?->label ?? $this->type
            : $this->type);
    }

    protected static function booted(): void
    {
        static::creating(function (Property $property) {
            if (empty($property->slug)) {
                $base = Str::slug($property->name);
                $slug = $base;
                $counter = 1;
                while (static::withTrashed()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$counter++;
                }
                $property->slug = $slug;
            }
        });
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function unitTypes(): HasMany
    {
        return $this->hasMany(UnitType::class);
    }

    public function propertyRates(): HasMany
    {
        return $this->hasMany(PropertyRate::class);
    }

    public function activePropertyRates(): HasMany
    {
        return $this->hasMany(PropertyRate::class)
            ->where('is_active', true)
            ->orderByRaw(
                "case billing_unit when 'day' then 1 when 'week' then 2 when 'month' then 3 when 'year' then 4 else 5 end"
            )
            ->orderBy('billing_interval')
            ->orderBy('id');
    }

    public function defaultActivePropertyRate(?string $currency = null): ?PropertyRate
    {
        $preferredCurrency = app(MoneyConverter::class)->normalizeCurrency($currency);
        $rates = $this->relationLoaded('activePropertyRates')
            ? $this->activePropertyRates
            : $this->activePropertyRates()->get();

        return $rates->first(fn (PropertyRate $rate): bool => $rate->currency === $preferredCurrency)
            ?? $rates->first();
    }

    public function hasActivePropertyRate(): bool
    {
        return $this->relationLoaded('activePropertyRates')
            ? $this->activePropertyRates->isNotEmpty()
            : $this->activePropertyRates()->exists();
    }

    public function hasActiveLease(): bool
    {
        return $this->leases()->active()->exists();
    }

    public function hasActiveWholePropertyLease(): bool
    {
        return $this->activeWholePropertyLeases()->exists();
    }

    public function hasViableWholePropertyOffering(): bool
    {
        return $this->rental_mode->supportsWholePropertyRental()
            && $this->hasActivePropertyRate();
    }

    public function hasViableUnitTypeOffering(): bool
    {
        return $this->rental_mode->supportsUnitInventory()
            && $this->unitTypes()->viablePublicOffering()->exists();
    }

    /**
     * Unit properties retain their existing property-level publication
     * semantics. Hybrid properties need at least one independently viable
     * public offering path.
     */
    public function hasViablePublicOffering(): bool
    {
        return static::query()
            ->whereKey($this)
            ->viablePublicOffering()
            ->exists();
    }

    public function rentalModeChangeError(PropertyRentalMode $requestedMode): ?string
    {
        if ($requestedMode === $this->rental_mode) {
            return null;
        }

        if ($this->is_published) {
            return __('Unpublish the property before changing its rental model.');
        }

        if ($requestedMode === PropertyRentalMode::WholeProperty
            && $this->rental_mode->supportsUnitInventory()
            && $this->leases()->active()->unitTarget()->exists()
        ) {
            return __('A property with active unit leases cannot change to Whole property.');
        }

        if ($requestedMode === PropertyRentalMode::Unit
            && $this->rental_mode->supportsWholePropertyRental()
            && $this->hasActiveWholePropertyLease()
        ) {
            return __('A property with an active whole-property lease cannot change to Unit inventory.');
        }

        return null;
    }

    public function isPubliclyVisible(): bool
    {
        return ! $this->trashed()
            && $this->is_active
            && $this->is_published
            && filled($this->public_slug)
            && $this->hasViablePublicOffering();
    }

    public function scopePubliclyVisible(Builder $query): void
    {
        $query
            ->where('properties.is_active', true)
            ->where('properties.is_published', true)
            ->whereNotNull('properties.public_slug')
            ->where('properties.public_slug', '<>', '')
            ->viablePublicOffering();
    }

    public function scopeViablePublicOffering(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query
                ->where(function (Builder $query): void {
                    $query
                        ->where('properties.rental_mode', PropertyRentalMode::Unit->value)
                        ->whereHas('unitTypes', fn (Builder $query) => $query->viablePublicOffering());
                })
                ->orWhere(function (Builder $query): void {
                    $query
                        ->where('properties.rental_mode', PropertyRentalMode::WholeProperty->value)
                        ->whereHas('propertyRates', fn (Builder $query) => $query->where('is_active', true));
                })
                ->orWhere(function (Builder $query): void {
                    $query
                        ->where('properties.rental_mode', PropertyRentalMode::Hybrid->value)
                        ->where(function (Builder $query): void {
                            $query
                                ->whereHas('propertyRates', fn (Builder $query) => $query->where('is_active', true))
                                ->orWhereHas('unitTypes', fn (Builder $query) => $query->viablePublicOffering());
                        });
                });
        });
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_property')
            ->using(AmenityProperty::class)
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function activeLeases(): HasMany
    {
        return $this->leases()->active();
    }

    public function activeWholePropertyLeases(): HasMany
    {
        return $this->activeLeases()->wholePropertyTarget();
    }

    public function scopeSupportsUnitInventory(Builder $query): void
    {
        $query->where('properties.rental_mode', '<>', PropertyRentalMode::WholeProperty->value);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    /**
     * Everything the property workspace header/tabs need.
     */
    public function scopeWithWorkspaceStats(Builder $query): void
    {
        $query->with(['city', 'region', 'propertyType'])
            ->withCount('units')
            ->withOccupiedUnitsCount()
            ->withTenantsCount();
    }

    public function scopeWithOccupiedUnitsCount(Builder $query): void
    {
        $query->withCount(['units as occupied_units_count' => fn (Builder $q) => $q->where(function (Builder $q) {
            $q->where('status', UnitStatus::Occupied)
                ->orWhereHas('leases', fn (Builder $q) => $q->active())
                ->orWhereHas('property', fn (Builder $q) => $q->whereHas('activeWholePropertyLeases'));
        })]);
    }

    public function scopeWithTenantsCount(Builder $query): void
    {
        $query->addSelect([
            'tenants_count' => Lease::query()
                ->selectRaw('COALESCE(COUNT(DISTINCT lease_tenant.tenant_id), 0)')
                ->join('lease_tenant', 'lease_tenant.lease_id', '=', 'leases.id')
                ->whereColumn('leases.property_id', 'properties.id')
                ->active(),
        ]);
    }

    public function scopeStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'active' => $query->where('properties.is_active', true),
            'archived' => $query->where('properties.is_active', false),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function scopeListSearch(Builder $query, string $search): void
    {
        $search = mb_strtolower($search);

        $query->where(function (Builder $query) use ($search): void {
            $query->whereRaw('lower(properties.name) like ?', ["%{$search}%"])
                ->orWhereHas('region', fn (Builder $region) => $region->whereRaw('lower(name) like ?', ["%{$search}%"]))
                ->orWhereHas('city', fn (Builder $city) => $city->whereRaw('lower(name) like ?', ["%{$search}%"]));
        });
    }
}
