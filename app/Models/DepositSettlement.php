<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\DepositSettlementStatus;
use Brick\Math\BigDecimal;
use Database\Factories\DepositSettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'lease_id',
    'original_amount',
    'currency',
    'status',
    'settlement_date',
    'refund_amount',
    'refund_reference',
    'notes',
])]
class DepositSettlement extends Model
{
    /** @use HasFactory<DepositSettlementFactory> */
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected $appends = ['deductions_total'];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:3',
            'refund_amount' => 'decimal:3',
            'settlement_date' => 'date:Y-m-d',
            'status' => DepositSettlementStatus::class,
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(DepositDeduction::class);
    }

    public function getDeductionsTotalAttribute(): string
    {
        $sum = $this->getAttribute('deductions_sum_amount');

        if ($sum !== null) {
            return (string) $sum;
        }

        if (! $this->relationLoaded('deductions')) {
            return '0';
        }

        return $this->deductions->reduce(
            fn (BigDecimal $total, DepositDeduction $deduction): BigDecimal => $total->plus((string) $deduction->amount),
            BigDecimal::zero(),
        )->toString();
    }
}
