<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'room_id',
    'start_date',
    'end_date',
    'monthly_rent',
    'deposit_amount',
    'deposit_paid_at',
    'deposit_refund_amount',
    'deposit_refunded_at',
    'rent_due_day',
    'status',
    'termination_date',
    'termination_reason',
    'notes',
])]
class Lease extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_rent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_refund_amount' => 'decimal:2',
            'deposit_paid_at' => 'datetime',
            'deposit_refunded_at' => 'datetime',
            'rent_due_day' => 'integer',
            'termination_date' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
