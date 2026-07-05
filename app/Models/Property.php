<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'type',
    'slug',
    'address',
    'region_id',
    'city_id',
    'postal_code',
    'phone',
    'description',
    'is_active',
])]
class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $with = ['region', 'city', 'propertyType'];

    protected $appends = ['type_label'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'type', 'slug');
    }

    /**
     * Human-readable type name, falling back to the raw slug if the type row
     * is missing (e.g. deleted).
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn () => $this->propertyType?->label ?? $this->type);
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

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function leases(): HasManyThrough
    {
        return $this->hasManyThrough(Lease::class, Room::class);
    }

    /**
     * Everything the property workspace header/tabs need.
     */
    public function scopeWithWorkspaceStats(Builder $query): void
    {
        $query->with(['city', 'region'])
            ->withCount('rooms')
            ->withOccupiedRoomsCount()
            ->withTenantsCount();
    }

    public function scopeWithOccupiedRoomsCount(Builder $query): void
    {
        $query->withCount(['rooms as occupied_rooms_count' => fn (Builder $q) => $q->where(function (Builder $q) {
            $q->where('status', RoomStatus::Occupied)
                ->orWhereHas('leases', fn (Builder $q) => $q->where('status', 'active'));
        })]);
    }

    public function scopeWithTenantsCount(Builder $query): void
    {
        $query->addSelect([
            'tenants_count' => DB::table('leases')
                ->selectRaw('COALESCE(COUNT(DISTINCT lease_tenant.tenant_id), 0)')
                ->join('lease_tenant', 'lease_tenant.lease_id', '=', 'leases.id')
                ->join('rooms', 'rooms.id', '=', 'leases.room_id')
                ->whereColumn('rooms.property_id', 'properties.id')
                ->where('leases.status', 'active'),
        ]);
    }
}
