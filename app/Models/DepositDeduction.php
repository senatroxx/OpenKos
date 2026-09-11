<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\DepositSettlementStatus;
use Database\Factories\DepositDeductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'deposit_settlement_id',
    'amount',
    'reason',
    'description',
])]
class DepositDeduction extends Model
{
    /** @use HasFactory<DepositDeductionFactory> */
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected static function booted(): void
    {
        static::creating(function (DepositDeduction $deduction): void {
            $deduction->guardMutable();
        });

        static::updating(function (DepositDeduction $deduction): void {
            $deduction->guardMutable();
        });

        static::deleting(function (DepositDeduction $deduction): void {
            $deduction->guardMutable();
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(DepositSettlement::class, 'deposit_settlement_id');
    }

    private function guardMutable(): void
    {
        if ($this->settlement()->where('status', DepositSettlementStatus::Settled->value)->exists()) {
            throw new LogicException('Deduction lines for settled deposit settlements are immutable.');
        }
    }
}
