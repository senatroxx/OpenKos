<?php

namespace App\Models;

use App\Enums\BillingUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'primary_tenant_id',
    'room_id',
    'start_date',
    'end_date',
    'rent_amount',
    'billing_interval',
    'billing_unit',
    'is_custom_price',
    'room_rate_id',
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

    protected $appends = ['monthly_equivalent', 'billing_label'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'decimal:2',
            'billing_interval' => 'integer',
            'billing_unit' => BillingUnit::class,
            'is_custom_price' => 'boolean',
            'deposit_amount' => 'decimal:2',
            'deposit_refund_amount' => 'decimal:2',
            'deposit_paid_at' => 'datetime',
            'deposit_refunded_at' => 'datetime',
            'rent_due_day' => 'integer',
            'termination_date' => 'date',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'primary_tenant_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomRate(): BelongsTo
    {
        return $this->belongsTo(RoomRate::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }

    public function getMonthlyEquivalentAttribute(): string
    {
        $amount = $this->rent_amount ? (float) $this->rent_amount : 0;
        $interval = $this->billing_interval ?? 1;
        $unit = $this->billing_unit ?? BillingUnit::Month;

        return match ($unit) {
            BillingUnit::Day => number_format($amount * 365 / 12 / $interval, 2, '.', ''),
            BillingUnit::Week => number_format($amount * 52 / 12 / $interval, 2, '.', ''),
            BillingUnit::Month => number_format($amount / $interval, 2, '.', ''),
            BillingUnit::Year => number_format($amount * 12 / $interval, 2, '.', ''),
        };
    }

    public function getBillingLabelAttribute(): string
    {
        $interval = $this->billing_interval ?? 1;
        $unit = $this->billing_unit ?? BillingUnit::Month;

        if ($unit === BillingUnit::Month && $interval === 1) {
            return '/month';
        }

        return match ($unit) {
            BillingUnit::Day => '/day',
            BillingUnit::Week => '/week',
            BillingUnit::Month => "/ {$interval} months",
            BillingUnit::Year => '/year',
        };
    }
}
