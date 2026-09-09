<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\UtilityReadingKind;
use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'utility_meter_id',
    'reading_kind',
    'reading_date',
    'period_start',
    'period_end',
    'previous_reading_id',
    'previous_reading',
    'current_reading',
    'consumption',
    'adjustment_consumption',
    'rate',
    'currency',
    'reference',
    'corrects_reading_id',
])]
class UtilityReading extends Model
{
    use Auditable, HasFactory, SerializesDatesWithTimezone;

    protected $attributes = [
        'reading_kind' => UtilityReadingKind::Reading,
    ];

    protected static function booted(): void
    {
        static::creating(function (UtilityReading $reading): void {
            $reading->currency = app(MoneyConverter::class)->normalizeCurrency($reading->currency);
            $reading->rate = app(MoneyConverter::class)->normalizeAmount(
                (string) $reading->rate,
                $reading->currency,
            );
        });

        static::updating(function (UtilityReading $reading): void {
            $reading->guardMutable();

            if ($reading->isDirty(['utility_meter_id', 'reading_kind', 'rate', 'currency'])) {
                throw new LogicException('Utility reading identity and rate snapshots cannot be changed.');
            }
        });

        static::deleting(function (UtilityReading $reading): void {
            $reading->guardMutable();
        });
    }

    protected function casts(): array
    {
        return [
            'reading_kind' => UtilityReadingKind::class,
            'reading_date' => 'date:Y-m-d',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'previous_reading' => 'decimal:3',
            'current_reading' => 'decimal:3',
            'consumption' => 'decimal:3',
            'adjustment_consumption' => 'decimal:3',
            'rate' => 'decimal:3',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(UtilityMeter::class, 'utility_meter_id');
    }

    public function previousReading(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_reading_id');
    }

    public function dependentReadings(): HasMany
    {
        return $this->hasMany(self::class, 'previous_reading_id');
    }

    public function correctsReading(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_reading_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_reading_id');
    }

    public function invoiceLineItem(): HasOne
    {
        return $this->hasOne(InvoiceLineItem::class);
    }

    public function isBilled(): bool
    {
        return $this->invoiceLineItem()->exists();
    }

    public function hasDependents(): bool
    {
        return $this->dependentReadings()->exists();
    }

    public function getCurrencyAttribute(?string $value): string
    {
        return app(MoneyConverter::class)->normalizeCurrency($value);
    }

    private function guardMutable(): void
    {
        if ($this->isBilled()) {
            throw new LogicException('Billed utility readings cannot be changed.');
        }

        if ($this->hasDependents()) {
            throw new LogicException('Utility readings used by later readings cannot be changed.');
        }
    }
}
