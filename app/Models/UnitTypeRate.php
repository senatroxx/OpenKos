<?php

namespace App\Models;

use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\BillingUnit;
use App\Services\Payments\MoneyConverter;
use Database\Factories\UnitTypeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'unit_type_id', 'billing_interval', 'billing_unit', 'amount', 'currency',
    'is_active', 'effective_from', 'effective_until',
])]
class UnitTypeRate extends Model
{
    /** @use HasFactory<UnitTypeRateFactory> */
    use HasFactory, SerializesDatesWithTimezone;

    protected function casts(): array
    {
        return [
            'billing_interval' => 'integer',
            'billing_unit' => BillingUnit::class,
            'amount' => 'decimal:3',
            'is_active' => 'boolean',
            'effective_from' => 'date:Y-m-d',
            'effective_until' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UnitTypeRate $rate): void {
            $rate->currency = app(MoneyConverter::class)->normalizeCurrency($rate->currency);
        });

        static::updating(function (UnitTypeRate $rate): void {
            if ($rate->isDirty(['unit_type_id', 'billing_interval', 'billing_unit', 'currency'])) {
                throw new LogicException('Unit Type rate identity cannot be changed after creation.');
            }
        });
    }

    public function getCurrencyAttribute(?string $value): string
    {
        return app(MoneyConverter::class)->normalizeCurrency($value);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }
}
