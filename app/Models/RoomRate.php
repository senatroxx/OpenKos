<?php

namespace App\Models;

use App\Enums\BillingUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'room_id',
    'billing_interval',
    'billing_unit',
    'amount',
    'is_active',
    'effective_from',
    'effective_until',
])]
class RoomRate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'billing_interval' => 'integer',
            'billing_unit' => BillingUnit::class,
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
