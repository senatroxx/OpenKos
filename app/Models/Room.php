<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'property_id',
    'name',
    'floor',
    'description',
    'size_sqm',
    'capacity',
    'status',
    'notes',
])]
class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'size_sqm' => 'decimal:2',
            'capacity' => 'integer',
            'status' => RoomStatus::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class);
    }

    public function activeRates(): HasMany
    {
        return $this->hasMany(RoomRate::class)
            ->whereRaw('is_active is true')
            ->orderBy('billing_interval');
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class);
    }
}
