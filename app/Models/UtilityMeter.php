<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\UtilityMeterType;
use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'unit_id',
    'utility_type',
    'identifier',
    'measurement_unit',
    'rate',
    'currency',
    'is_active',
])]
class UtilityMeter extends Model
{
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (UtilityMeter $meter): void {
            $meter->currency = app(MoneyConverter::class)->normalizeCurrency(
                $meter->getAttributeFromArray('currency'),
            );
            $meter->rate = app(MoneyConverter::class)->normalizeAmount(
                (string) $meter->rate,
                $meter->currency,
            );
        });

        static::updating(function (UtilityMeter $meter): void {
            if (! $meter->isDirty(['rate', 'currency'])) {
                return;
            }

            $meter->currency = app(MoneyConverter::class)->normalizeCurrency($meter->currency);
            $meter->rate = app(MoneyConverter::class)->normalizeAmount(
                (string) $meter->rate,
                $meter->currency,
            );
        });
    }

    protected function casts(): array
    {
        return [
            'utility_type' => UtilityMeterType::class,
            'rate' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(UtilityReading::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getCurrencyAttribute(?string $value): string
    {
        return app(MoneyConverter::class)->normalizeCurrency($value);
    }
}
