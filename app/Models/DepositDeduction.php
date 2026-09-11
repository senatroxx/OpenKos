<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use Database\Factories\DepositDeductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
