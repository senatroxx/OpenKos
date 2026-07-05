<?php

namespace App\Models;

use App\Enums\BillingUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'unit_id',
    'billing_interval',
    'billing_unit',
    'amount',
    'is_active',
    'effective_from',
    'effective_until',
])]
class UnitRate extends Model
{
    use HasFactory;

    protected $table = 'unit_rates';

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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
