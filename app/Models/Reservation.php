<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'application_id', 'unit_id', 'lease_id', 'status', 'move_in_date',
        'confirmed_at', 'expires_at', 'rejected_at', 'cancelled_at', 'expired_at', 'converted_at',
        'confirmed_by', 'rejected_by', 'cancelled_by', 'converted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'move_in_date' => 'date',
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function scopeHolding(Builder $query): void
    {
        $table = $query->getModel()->getTable();

        $query->where("{$table}.status", ReservationStatus::Confirmed->value)
            ->where("{$table}.expires_at", '>', now());
    }

    public function scopeForProperty(Builder $query, Property|int $property): void
    {
        $propertyId = $property instanceof Property ? $property->getKey() : $property;

        $query->whereHas('application', fn (Builder $query) => $query->where('property_id', $propertyId));
    }

    public function scopeOverlappingLeasePeriod(Builder $query, ?string $leaseEndDate): void
    {
        if ($leaseEndDate !== null) {
            $query->whereDate($query->getModel()->qualifyColumn('move_in_date'), '<=', $leaseEndDate);
        }
    }
}
