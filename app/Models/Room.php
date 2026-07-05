<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'property_id',
    'name',
    'slug',
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
            'size_sqm' => 'decimal:2',
            'capacity' => 'integer',
            'status' => RoomStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Room $room) {
            if (empty($room->slug)) {
                $base = Str::slug($room->name) ?: 'room';
                $slug = $base;
                $counter = 1;
                while (static::withTrashed()
                    ->where('property_id', $room->property_id)
                    ->where('slug', $slug)
                    ->exists()
                ) {
                    $slug = $base.'-'.++$counter;
                }
                $room->slug = $slug;
            }
        });
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

    public function scopeAvailableForAssignment(Builder $query): void
    {
        $query->whereNull('deleted_at')
            ->whereNotIn('status', ['maintenance'])
            ->where(function (Builder $q) {
                $q->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active'))
                    ->orWhereRaw('capacity > (SELECT COALESCE(COUNT(*), 0) FROM lease_tenant WHERE lease_id IN (SELECT id FROM leases WHERE room_id = rooms.id AND status = \'active\'))');
            });
    }

    public function scopeWithOccupiedCount(Builder $query): void
    {
        $query->addSelect([
            'occupied_count' => DB::table('lease_tenant')
                ->selectRaw('COALESCE(COUNT(*), 0)')
                ->whereIn('lease_id', function (\Illuminate\Database\Query\Builder $q) {
                    $q->select('id')
                        ->from('leases')
                        ->whereColumn('room_id', 'rooms.id')
                        ->where('status', 'active');
                }),
        ]);
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class);
    }
}
